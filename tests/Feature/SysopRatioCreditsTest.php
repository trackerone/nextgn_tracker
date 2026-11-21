<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserRatioCredit;
use App\Models\UserStat;
use App\Services\Tracker\DownloadEligibilityPolicy;
use App\Services\Tracker\UserRatioBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SysopRatioCreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sysop_can_view_and_change_ratio_credits(): void
    {
        $member = User::factory()->create();
        $this->get(route('sysop.ratio-credits.index'))->assertRedirect(route('login'));

        foreach ([User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)
                ->get(route('sysop.ratio-credits.index'))
                ->assertForbidden();
            $this->post(route('sysop.ratio-credits.users.adjust', $member), [
                'metric' => 'uploaded',
                'direction' => 'add',
                'amount_gib' => '1',
                'audit_reason' => 'Not authorized.',
            ])->assertForbidden();
        }

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->get(route('sysop.ratio-credits.index'))
            ->assertOk()
            ->assertSee('Starting balance for new members')
            ->assertSee('Adjust an existing member');
    }

    public function test_topbar_shows_live_effective_ratio_without_redundant_account_menu_item(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        UserStat::query()->create([
            'user_id' => $member->getKey(),
            'uploaded_bytes' => 1073741824,
            'downloaded_bytes' => 2147483648,
        ]);

        $this->actingAs($member);
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('aria-label="My uploaded, downloaded and ratio statistics"', false)
            ->assertSee('href="'.route('account.snatches').'"', false)
            ->assertSee('R 0.50', false)
            ->assertSee('text-rose-300', false)
            ->assertDontSee('Ratio & snatches');

        $account = $this->get(route('account.index'))->assertOk();
        $html = (string) $account->getContent();
        $this->assertSame(1, substr_count($html, 'aria-label="My uploaded, downloaded and ratio statistics"'));
        $navStart = strpos($html, 'aria-label="Account navigation"');
        $this->assertNotFalse($navStart);
        $navEnd = strpos($html, '</nav>', $navStart);
        $this->assertNotFalse($navEnd);
        $this->assertStringNotContainsString('Completed torrents', substr($html, $navStart, $navEnd - $navStart));
    }

    public function test_audited_credits_affect_header_api_and_download_eligibility_without_rewriting_traffic(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $torrent = Torrent::factory()->create();

        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 107374182,
            'downloaded_bytes' => 1073741824,
        ]);

        $this->assertFalse(app(DownloadEligibilityPolicy::class)->check($member, $torrent)['allowed']);

        $this->actingAs($sysop)
            ->post(route('sysop.ratio-credits.users.adjust', $member), [
                'metric' => 'uploaded',
                'direction' => 'add',
                'amount_gib' => '1',
                'audit_reason' => 'Compensate verified historic account balance.',
            ])->assertRedirect(route('sysop.ratio-credits.index', ['user' => $member->id]));

        $this->assertDatabaseHas('user_ratio_credits', [
            'user_id' => $member->id,
            'uploaded_credit_bytes' => 1073741824,
            'downloaded_credit_bytes' => 0,
        ]);
        $this->assertDatabaseHas('user_stats', [
            'user_id' => $member->id,
            'uploaded_bytes' => 107374182,
            'downloaded_bytes' => 1073741824,
        ]);
        $this->assertTrue(app(DownloadEligibilityPolicy::class)->check($member, $torrent)['allowed']);

        $this->actingAs($member)->get(route('home'))->assertOk()->assertSee('R 1.10', false);
        $this->getJson('/api/me/stats')->assertOk()
            ->assertJsonPath('uploaded_bytes', 1073741824 + 107374182)
            ->assertJsonPath('downloaded_bytes', 1073741824);
        $this->assertEqualsWithDelta(1.1, $member->ratio(), 0.0001);

        $audit = AuditLog::query()->where('action', 'sysop.ratio.credit_adjusted')->firstOrFail();
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertSame($member->id, $audit->target_id);
        $this->assertSame('uploaded', $audit->metadata['metric']);
        $this->assertSame('Compensate verified historic account balance.', $audit->metadata['reason']);
    }

    public function test_sysop_can_take_upload_or_download_credit_but_cannot_make_balance_negative(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 2 * 1073741824,
            'downloaded_bytes' => 1073741824,
        ]);
        $this->actingAs($sysop);

        $this->post(route('sysop.ratio-credits.users.adjust', $member), [
            'metric' => 'uploaded', 'direction' => 'subtract',
            'amount_gib' => '1', 'audit_reason' => 'Reverse prior mistaken credit.',
        ])->assertRedirect();
        $this->post(route('sysop.ratio-credits.users.adjust', $member), [
            'metric' => 'downloaded', 'direction' => 'subtract',
            'amount_gib' => '0.5', 'audit_reason' => 'Correct erroneous download charge.',
        ])->assertRedirect();

        $balance = app(UserRatioBalance::class)->totals($member);
        $this->assertSame(1073741824, $balance['uploaded']);
        $this->assertSame(536870912, $balance['downloaded']);
        $this->assertDatabaseHas('user_ratio_credits', [
            'user_id' => $member->id,
            'uploaded_credit_bytes' => -1073741824,
            'downloaded_credit_bytes' => -536870912,
        ]);
        $this->assertDatabaseHas('user_stats', [
            'user_id' => $member->id,
            'uploaded_bytes' => 2 * 1073741824,
            'downloaded_bytes' => 1073741824,
        ]);

        $this->post(route('sysop.ratio-credits.users.adjust', $member), [
            'metric' => 'downloaded', 'direction' => 'subtract',
            'amount_gib' => '1', 'audit_reason' => 'Too large.',
        ])->assertSessionHasErrors('amount_gib');

        $this->assertSame(536870912, app(UserRatioBalance::class)->totals($member)['downloaded']);
        $this->assertSame(2, AuditLog::query()->where('action', 'sysop.ratio.credit_adjusted')->count());
    }

    public function test_starting_credits_only_apply_to_accounts_registered_after_sysop_change(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $oldMember = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)->patch(route('sysop.ratio-credits.defaults.update'), [
            'uploaded_gib' => '5',
            'downloaded_gib' => '1.25',
            'audit_reason' => 'Give new members a measured starting balance.',
        ])->assertRedirect(route('sysop.ratio-credits.index'));

        $defaults = app(UserRatioBalance::class)->registrationDefaults();
        $this->assertSame(5.0, $defaults['uploaded']);
        $this->assertSame(1.25, $defaults['downloaded']);
        $this->assertSame(0, app(UserRatioBalance::class)->totals($oldMember)['uploaded']);
        $this->assertDatabaseMissing('user_ratio_credits', ['user_id' => $oldMember->id]);

        $invite = Invite::factory()->create(['inviter_user_id' => $sysop->id, 'max_uses' => 1, 'uses' => 0]);
        auth()->logout();
        $this->flushSession();
        $this->post('/register', [
            'name' => 'New Credit Member',
            'email' => 'credit-member@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invite_code' => $invite->code,
        ])->assertRedirect('/');

        $member = User::query()->where('email', 'credit-member@example.test')->firstOrFail();
        $this->assertDatabaseHas('user_ratio_credits', [
            'user_id' => $member->id,
            'uploaded_credit_bytes' => 5 * 1073741824,
            'downloaded_credit_bytes' => 1342177280,
        ]);
        $this->assertSame(5 * 1073741824, app(UserRatioBalance::class)->totals($member)['uploaded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sysop.ratio.defaults_updated', 'user_id' => $sysop->id]);
    }
}
