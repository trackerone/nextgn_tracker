<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HnrAccount;
use App\Models\HnrObligation;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Hnr\HnrService;
use App\Services\Hnr\HnrSettings;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Tracker\AnnounceIntegrityEvaluation;
use App\Services\Tracker\DownloadEligibilityPolicy;
use App\Tracker\Announce\AnnounceRequestData;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HnrTest extends TestCase
{
    public function test_hnr_defaults_off_and_sysop_can_save_validated_rules_with_audit(): void
    {
        $this->assertFalse(app(HnrSettings::class)->enabled());
        $this->assertSame(72, app(HnrSettings::class)->values()['seed_hours']);
        $this->get(route('sysop.hnr.edit'))->assertRedirect(route('login'));
        foreach ([User::ROLE_USER, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('sysop.hnr.edit'))->assertForbidden();
            $this->patch(route('sysop.hnr.update'), HnrSettings::DEFAULTS)->assertForbidden();
        }
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->get(route('sysop.hnr.edit'))->assertOk()->assertSee('Required seed hours');
        $rules = array_replace(HnrSettings::DEFAULTS, ['enabled' => true, 'seed_hours' => 24]);
        $this->patch(route('sysop.hnr.update'), $rules)->assertRedirect(route('sysop.hnr.edit'));
        $this->assertSame(24, app(HnrSettings::class)->values()['seed_hours']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sysop.hnr_settings.updated']);
        $this->patch(route('sysop.hnr.update'), array_replace($rules, ['seed_hours' => 0]))->assertSessionHasErrors('seed_hours');
        $this->assertSame(24, app(HnrSettings::class)->values()['seed_hours']);
    }

    public function test_freeleech_and_partial_downloads_use_validated_physical_bytes(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['is_freeleech' => true]);
        $service = app(HnrService::class);
        $service->record($user, $torrent, $this->data(left: 500), $this->credit(100, 200), null);
        $row = HnrObligation::query()->firstOrFail();
        $this->assertSame(200, $row->downloaded_bytes);
        $this->assertSame(100, $row->uploaded_bytes);
        $this->assertNull($row->completed_at);
        $this->assertNull($row->fulfilled_at);
        $service->record($user, $torrent, $this->data(left: 500), $this->credit(100, 0), null);
        $this->assertNotNull($row->fresh()->fulfilled_at);
    }

    public function test_no_obligation_is_created_for_uncredited_initial_seed_or_disabled_or_exempt_role(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $hnr = app(HnrService::class);
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 200), null);
        $this->enable();
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 0), null);
        foreach ([User::ROLE_SYSOP, User::ROLE_UPLOADER] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $hnr->record($staff, $torrent, $this->data(), $this->credit(0, 200), null);
        }
        $this->assertSame(0, HnrObligation::query()->count());
    }

    public function test_completed_seed_time_does_not_count_offline_or_twice_for_multiple_clients(): void
    {
        $this->enable(['seed_hours' => 1]);
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $hnr = app(HnrService::class);
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 1000), null);
        $peer = Peer::factory()->for($user)->for($torrent)->create(['left' => 0, 'last_announce_at' => now()]);
        $this->travel(30)->minutes();
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 0), $peer);
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 0), $peer);
        $this->assertSame(1800, HnrObligation::query()->firstOrFail()->seed_seconds);
        $peer->last_announce_at = now();
        $this->travel(30)->minutes();
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 0), $peer);
        $row = HnrObligation::query()->firstOrFail();
        $this->assertSame(3600, $row->seed_seconds);
        $this->assertNotNull($row->fulfilled_at);
        $this->travel(2)->days();
        $hnr->record($user, $torrent, $this->data(), $this->credit(0, 0), $peer);
        $this->assertSame(3600, $row->fresh()->seed_seconds);
    }

    public function test_partial_downloads_do_not_earn_seed_time_and_policy_changes_are_not_retroactive(): void
    {
        $this->enable(['seed_hours' => 1]);
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $hnr = app(HnrService::class);
        $hnr->record($user, $torrent, $this->data(left: 500), $this->credit(0, 1000), null);
        $peer = Peer::factory()->for($user)->for($torrent)->create(['left' => 500, 'last_announce_at' => now()]);
        $this->travel(30)->minutes();
        $this->enable(['seed_hours' => 24]);
        $hnr->record($user, $torrent, $this->data(left: 500), $this->credit(0, 0), $peer);
        $row = HnrObligation::query()->firstOrFail();
        $this->assertSame(0, $row->seed_seconds);
        $this->assertSame(3600, $row->required_seed_seconds);
    }

    public function test_threshold_grace_cooldown_restriction_and_recovery(): void
    {
        $this->enable(['torrent_threshold' => 1, 'grace_hours' => 1, 'warning_interval_days' => 1, 'warning_decay_days' => 2, 'warnings_before_restriction' => 1]);
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $hnr = app(HnrService::class);
        $hnr->evaluate($user);
        $this->assertSame(0, HnrAccount::query()->firstOrFail()->warnings);
        $this->travel(1)->hours();
        $hnr->evaluate($user);
        $hnr->evaluate($user);
        $this->assertSame(1, HnrAccount::query()->firstOrFail()->warnings);
        $this->assertSame(1, DB::table('hnr_events')->where('type', 'warning')->count());
        $this->assertFalse($hnr->restricted($user));
        $this->travel(1)->days();
        $hnr->evaluate($user);
        $this->assertTrue($hnr->restricted($user));
        $this->assertSame('hnr_restricted', app(DownloadEligibilityPolicy::class)->check($user, Torrent::factory()->create())['reason']);
        $this->assertSame('hnr_recovery', app(DownloadEligibilityPolicy::class)->check($user, $row->torrent)['reason']);
        Peer::factory()->for($user)->for($row->torrent)->create(['last_announce_at' => now(), 'left' => 0]);
        $hnr->evaluate($user);
        $this->assertFalse($hnr->restricted($user));
        $this->assertFalse($user->fresh()->is_disabled);
    }

    public function test_active_and_unavailable_torrents_do_not_trigger_warnings(): void
    {
        $this->enable(['torrent_threshold' => 1, 'grace_hours' => 1]);
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $this->travel(2)->hours();
        Peer::factory()->for($user)->for($row->torrent)->create(['last_announce_at' => now()]);
        app(HnrService::class)->evaluate($user);
        $this->assertSame(0, HnrAccount::query()->firstOrFail()->warnings);
        $this->travel(2)->hours();
        $row->torrent->update(['is_banned' => true]);
        app(HnrService::class)->evaluate($user);
        $this->assertSame(0, HnrAccount::query()->firstOrFail()->warnings);
    }

    public function test_warnings_expire_one_per_clean_interval_without_underflow(): void
    {
        $this->enable(['warning_decay_days' => 2]);
        $user = User::factory()->create();
        $state = HnrAccount::query()->create(['user_id' => $user->id, 'warnings' => 3, 'decay_at' => now()->addDays(2)]);
        $this->travel(4)->days();
        app(HnrService::class)->evaluate($user);
        $this->assertSame(1, $state->fresh()->warnings);
        $this->travel(10)->days();
        app(HnrService::class)->evaluate($user);
        $this->assertSame(0, $state->fresh()->warnings);
    }

    public function test_waiver_is_audited_survives_announces_and_only_sysop_can_reset(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $this->actingAs($user)->post(route('sysop.hnr.waive', $row), ['reason' => 'test'])->assertForbidden();
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->post(route('sysop.hnr.waive', $row), [])->assertSessionHasErrors('reason');
        $this->post(route('sysop.hnr.waive', $row), ['reason' => 'Storage failure reviewed'])->assertRedirect();
        app(HnrService::class)->record($user, $row->torrent, $this->data(), $this->credit(0, 100), null);
        $this->assertNotNull($row->fresh()->waived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sysop.hnr_obligation.waived']);
        HnrAccount::query()->where('user_id', $user->id)->update(['warnings' => 2, 'restricted_at' => now()]);
        $this->post(route('sysop.hnr.reset', $user->publicProfileRouteKey()), ['reason' => 'Appeal accepted'])->assertRedirect();
        $this->assertSame(0, HnrAccount::query()->where('user_id', $user->id)->firstOrFail()->warnings);
        $this->assertSame(1, HnrObligation::query()->count());
    }

    public function test_hnr_pages_are_private_and_disabled_command_is_inert(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->obligation($other, 'Other member private torrent');
        $this->get(route('account.hnr'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('account.hnr'))->assertOk()->assertSee('HnR enforcement is disabled')->assertDontSee('Other member private torrent');
        $this->artisan('hnr:evaluate')->assertSuccessful();
        $this->assertSame(0, HnrAccount::query()->count());
    }

    public function test_idle_announces_keep_peer_fresh_and_credit_seed_time_without_double_transfer_credit(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $torrent = $row->torrent;
        $peerId = str_repeat('a', 20);
        Peer::factory()->for($user)->for($torrent)->create(['peer_id' => $peerId, 'uploaded' => 0, 'downloaded' => 1000, 'left' => 0, 'last_announce_at' => now()]);
        $url = '/announce/'.$user->ensurePasskey().'?'.http_build_query(['info_hash' => $torrent->info_hash, 'peer_id' => bin2hex($peerId), 'port' => 6881, 'uploaded' => 0, 'downloaded' => 1000, 'left' => 0]);
        for ($i = 0; $i < 3; $i++) {
            $this->travel(30)->minutes();
            $reply = $this->get($url)->assertOk();
            $this->assertArrayNotHasKey('failure reason', app(BencodeService::class)->decode($reply->getContent()));
        }
        $this->assertSame(5400, $row->fresh()->seed_seconds);
        $this->assertSame(1000, $row->fresh()->downloaded_bytes);
        $this->assertSame(now()->getTimestamp(), Peer::query()->where('user_id', $user->id)->firstOrFail()->last_announce_at->getTimestamp());
    }

    public function test_reenable_restarts_grace_and_repeated_stopped_reports_cannot_hide_overdue_obligations(): void
    {
        $this->enable(['enabled' => false]);
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $this->travel(10)->days();
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $rules = array_replace(HnrSettings::DEFAULTS, ['enabled' => true, 'grace_hours' => 1, 'torrent_threshold' => 1]);
        $this->actingAs($sysop)->patch(route('sysop.hnr.update'), $rules)->assertRedirect();
        $this->assertSame(0, app(HnrService::class)->overdueCount($user));
        $this->travel(2)->hours();
        $stopped = new AnnounceRequestData('', str_repeat('a', 20), 6881, 0, 0, 0, 'stopped', 50, null);
        app(HnrService::class)->record($user, $row->torrent, $stopped, $this->credit(0, 0), null);
        $this->assertSame(1, app(HnrService::class)->overdueCount($user));
    }

    public function test_new_downloading_announces_are_blocked_but_recovery_and_seeding_are_allowed(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $row = $this->obligation($user);
        HnrAccount::query()->create(['user_id' => $user->id, 'warnings' => 3, 'restricted_at' => now()]);
        $other = Torrent::factory()->create();
        $announce = function (Torrent $torrent, int $left) use ($user): array {
            $url = '/announce/'.$user->ensurePasskey().'?'.http_build_query(['info_hash' => $torrent->info_hash, 'peer_id' => bin2hex(str_repeat('b', 20)), 'port' => 6881, 'uploaded' => 0, 'downloaded' => 0, 'left' => $left, 'event' => 'started']);
            $reply = $this->get($url)->assertOk();

            return app(BencodeService::class)->decode($reply->getContent());
        };
        $this->assertArrayHasKey('failure reason', $announce($other, 1000));
        $this->assertArrayNotHasKey('failure reason', $announce($row->torrent, 1000));
        $this->assertArrayNotHasKey('failure reason', $announce($other, 0));
    }

    public function test_suspicious_reports_do_not_earn_seed_time(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $row = $this->obligation($user);
        $peer = Peer::factory()->for($user)->for($row->torrent)->create(['left' => 0, 'last_announce_at' => now()]);
        $this->travel(30)->minutes();
        app(HnrService::class)->record($user, $row->torrent, $this->data(), new AnnounceIntegrityEvaluation(0, 0, false, ['uploaded_implausible']), $peer);
        $this->assertSame(0, $row->fresh()->seed_seconds);
    }

    private function enable(array $overrides = []): void
    {
        foreach (array_replace(HnrSettings::DEFAULTS, ['enabled' => true], $overrides) as $key => $value) {
            app(SiteSettingsRepository::class)->set('hnr.'.$key, $value, is_bool($value) ? 'bool' : (is_float($value) ? 'float' : 'int'));
        }
    }

    private function obligation(User $user, string $name = 'Tracked release'): HnrObligation
    {
        $torrent = Torrent::factory()->create(['name' => $name]);

        return HnrObligation::query()->create(['user_id' => $user->id, 'torrent_id' => $torrent->id, 'downloaded_bytes' => 1000, 'required_seed_seconds' => 259200, 'required_ratio' => 1, 'completed_at' => now(), 'last_seen_at' => now(), 'last_counted_at' => now()]);
    }

    private function data(int $left = 0): AnnounceRequestData
    {
        return new AnnounceRequestData('', str_repeat('a', 20), 6881, 0, 0, $left, null, 50, null);
    }

    private function credit(int $up, int $down): AnnounceIntegrityEvaluation
    {
        return new AnnounceIntegrityEvaluation($up, $down, false, []);
    }
}
