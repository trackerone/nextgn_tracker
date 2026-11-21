<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardClassAndUnitsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_account_and_ratio_details_use_the_same_tenure_aware_class_and_gb_units(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create([
            'role' => User::ROLE_USER,
            'created_at' => now()->subDays(3),
        ]);

        UserStat::query()->create([
            'user_id' => $member->getKey(),
            'uploaded_bytes' => 112 * 1073741824,
            'downloaded_bytes' => 8 * 1073741824,
        ]);

        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), [
            'elite_min_ratio' => 1.5,
            'power_user_min_ratio' => 0.8,
            'power_user_min_downloaded' => 1000,
            'user_min_ratio' => 0.3,
            'tenure_enabled' => 1,
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ])->assertRedirect(route('sysop.permissions.index').'#class-thresholds');

        $this->actingAs($member);

        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('Class: Newbie')
            ->assertSee('Up 112.00 GB')
            ->assertSee('Down 8.00 GB')
            ->assertSee('14.00')
            ->assertDontSee('Class: Elite')
            ->assertDontSee('120,259,084,288 B')
            ->assertDontSee('8,589,934,592 B')
            ->assertDontSee('Welcome back')
            ->assertDontSee('share clean uploads');

        $this->get(route('account.index'))->assertOk()->assertSee('User / Newbie');
        $this->get(route('account.snatches'))->assertOk()
            ->assertSee('112.00 GB')
            ->assertSee('8.00 GB')
            ->assertSee('Newbie');

        $this->get(route('home'))->assertOk()
            ->assertSee('Class: Newbie')
            ->assertDontSee('Welcome back');
    }

    public function test_dashboard_class_still_uses_ratio_only_when_tenure_is_disabled(): void
    {
        $member = User::factory()->create(['created_at' => now()->subDays(3)]);
        UserStat::query()->create([
            'user_id' => $member->getKey(),
            'uploaded_bytes' => 112 * 1073741824,
            'downloaded_bytes' => 8 * 1073741824,
        ]);

        $this->actingAs($member)->get(route('home'))->assertOk()
            ->assertSee('Class: Elite')
            ->assertSee('Up 112.00 GB')
            ->assertSee('Down 8.00 GB')
            ->assertDontSee('Welcome back');
    }
}
