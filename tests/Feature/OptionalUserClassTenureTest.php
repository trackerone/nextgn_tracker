<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserStat;
use App\Services\Settings\RatioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OptionalUserClassTenureTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_time_gate_preserves_existing_ratio_based_classification(): void
    {
        $member = User::factory()->create(['created_at' => now()->subDays(2)]);
        $this->grantTraffic($member, 112, 8);

        $this->assertFalse(app(RatioSettings::class)->tenureEnabled());
        $this->assertSame('Elite', $member->userClass());
    }

    public function test_sysop_can_require_membership_age_alongside_ratio_for_each_class(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '1',
            'user_min_months' => '3',
            'power_user_min_months' => '12',
            'elite_min_months' => '36',
        ]))->assertRedirect(route('sysop.permissions.index').'#class-thresholds');

        $settings = app(RatioSettings::class);
        $this->assertTrue($settings->tenureEnabled());
        $this->assertSame(3, $settings->userMinMonths());
        $this->assertSame(12, $settings->powerUserMinMonths());
        $this->assertSame(36, $settings->eliteMinMonths());

        foreach ([
            [2, 'Newbie'],
            [3, 'User'],
            [11, 'User'],
            [12, 'Power User'],
            [35, 'Power User'],
            [36, 'Elite'],
        ] as [$months, $expected]) {
            $member = User::factory()->create([
                'role' => User::ROLE_USER,
                'created_at' => now()->subMonthsNoOverflow($months),
            ]);
            $this->grantTraffic($member, 112, 8);
            $this->assertSame($expected, $member->userClass(), 'Incorrect class after '.$months.' membership months');
            $this->assertSame(User::ROLE_USER, $member->fresh()->role, 'Class promotion must not elevate permission roles');
        }

        $newMemberWithoutTraffic = User::factory()->create();
        $this->assertSame('Newbie', $newMemberWithoutTraffic->userClass());
        $noTrafficVeteran = User::factory()->create(['created_at' => now()->subMonthsNoOverflow(3)]);
        $this->assertSame('User', $noTrafficVeteran->userClass());
    }

    public function test_duration_alone_cannot_bypass_the_ratio_thresholds(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '1',
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ]))->assertRedirect();

        $member = User::factory()->create(['created_at' => now()->subYears(4)]);
        $this->grantTraffic($member, 1, 8);
        $this->assertSame('Leech', $member->userClass());

        $member2 = User::factory()->create(['created_at' => now()->subYears(4)]);
        $this->grantTraffic($member2, 5, 8);
        $this->assertSame('User', $member2->userClass());

        $staff = User::factory()->create(['role' => User::ROLE_ADMIN, 'created_at' => now()->subDays(1)]);
        $this->assertSame('Staff', $staff->userClass());
    }

    public function test_sysop_can_disable_tenure_requirement_without_erasing_configured_months(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '1',
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ]))->assertRedirect();

        $member = User::factory()->create();
        $this->grantTraffic($member, 112, 8);
        $this->assertSame('Newbie', $member->userClass());

        $this->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '0',
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ]))->assertRedirect();

        $this->assertSame('Elite', $member->userClass());
        $this->assertSame('36', Setting::valueForKey('ratio.elite_min_months'));
        $this->assertSame('0', Setting::valueForKey('ratio.tenure_enabled'));
    }

    public function test_non_sysop_admin_can_change_ratio_but_cannot_change_tenure_policies(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => 1,
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ]))->assertRedirect(route('admin.settings.ratio.edit'));

        $this->assertFalse(app(RatioSettings::class)->tenureEnabled());
        $this->assertDatabaseMissing('settings', ['key' => 'ratio.tenure_enabled']);
        $this->get(route('admin.settings.ratio.edit'))->assertOk()
            ->assertDontSee('name="tenure_enabled"', false);
    }

    public function test_invalid_or_out_of_order_membership_months_are_rejected(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '1',
            'user_min_months' => 12,
            'power_user_min_months' => 3,
            'elite_min_months' => 36,
        ]))->assertSessionHasErrors('user_min_months');

        $this->patch(route('admin.settings.ratio.update'), $this->thresholds([
            'tenure_enabled' => '1',
            'user_min_months' => -1,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ]))->assertSessionHasErrors('user_min_months');

        $this->assertDatabaseMissing('settings', ['key' => 'ratio.tenure_enabled']);
    }

    public function test_completed_torrents_stat_cards_and_table_display_gb_not_raw_bytes(): void
    {
        $user = User::factory()->create();
        $this->grantTraffic($user, 112, 8);

        $this->actingAs($user)->get(route('account.snatches'))->assertOk()
            ->assertSee('112.00 GB')
            ->assertSee('8.00 GB')
            ->assertDontSee('120,259,084,288 B')
            ->assertDontSee('8,589,934,592 B');
    }

    public function test_same_admin_page_exposes_opt_in_age_controls_with_default_values(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->get(route('sysop.permissions.index'))->assertOk()
            ->assertSee('Require membership time for automatic promotion')
            ->assertSee('name="tenure_enabled"', false)
            ->assertSee('name="user_min_months"', false)
            ->assertSee('name="power_user_min_months"', false)
            ->assertSee('name="elite_min_months"', false)
            ->assertSee('value="36"', false);
    }

    private function grantTraffic(User $member, int $uploadedGiB, int $downloadedGiB): void
    {
        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => $uploadedGiB * 1073741824,
            'downloaded_bytes' => $downloadedGiB * 1073741824,
        ]);
    }

    private function thresholds(array $overrides = []): array
    {
        return array_merge([
            'elite_min_ratio' => 1.5,
            'power_user_min_ratio' => 0.8,
            'power_user_min_downloaded' => 1000,
            'user_min_ratio' => 0.3,
        ], $overrides);
    }
}
