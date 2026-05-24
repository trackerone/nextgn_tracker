<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class SysopOperationsDashboardTest extends TestCase
{
    public function test_guest_is_redirected(): void
    {
        $this->get(route('sysop.operations.index'))->assertRedirect(route('login'));
    }

    public function test_non_sysop_staff_is_denied(): void
    {
        $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR, 'is_staff' => true]);
        $this->actingAs($moderator)->get(route('sysop.operations.index'))->assertForbidden();
    }

    public function test_sysop_can_access_dashboard(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)
            ->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Sysop Operations Dashboard')
            ->assertSee('Application')
            ->assertSee('Production Readiness');
    }

    public function test_warning_state_is_shown_for_insecure_production_configuration(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('security.production_hardening_enabled', false);

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)
            ->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Overall state: Critical')
            ->assertSee('Disable APP_DEBUG in production deployments.');
    }

    public function test_unhealthy_dependencies_are_handled_gracefully(): void
    {
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('cache unavailable'));
        Artisan::shouldReceive('call')->andThrow(new \RuntimeException('scheduler unavailable'));

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)
            ->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Cache store: array (unavailable)')
            ->assertSee('Scheduler visibility: unavailable');
    }
}
