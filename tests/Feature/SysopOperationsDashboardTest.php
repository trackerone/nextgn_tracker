<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

    public function test_admin_without_sysop_role_is_denied_for_dashboard_and_runtime_toggle(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_staff' => true]);

        $this->actingAs($admin)->get(route('sysop.operations.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('sysop.operations.runtime-jobs.toggle'), [
            'job_key' => 'metadata.refresh',
            'enabled' => false,
        ])->assertForbidden();
    }

    public function test_sysop_can_access_dashboard(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)
            ->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Sysop Operations Dashboard')
            ->assertSee('Runtime Job Controls (Safe Scope)')
            ->assertSee('Immutable Critical')
            ->assertSee('Sysop Controllable')
            ->assertSee('Visibility only: scheduler actions are not available from this dashboard.')
            ->assertSee('Visibility only: retry/flush actions are intentionally disabled here.');
    }

    public function test_sysop_can_toggle_approved_job_and_state_persists_with_audit_log(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)->post(route('sysop.operations.runtime-jobs.toggle'), [
            'job_key' => 'metadata.refresh',
            'enabled' => false,
        ])->assertRedirect(route('sysop.operations.index'));

        $this->assertDatabaseHas('site_settings', ['key' => 'runtime.jobs.metadata.refresh.enabled', 'value' => 'false']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sysop.runtime_job_state_changed']);

        $audit = AuditLog::query()->where('action', 'sysop.runtime_job_state_changed')->latest('id')->firstOrFail();

        $this->assertSame('metadata.refresh', $audit->metadata['job_key'] ?? null);
        $this->assertSame(true, $audit->metadata['previous_state'] ?? null);
        $this->assertSame(false, $audit->metadata['new_state'] ?? null);
    }

    public function test_immutable_critical_job_cannot_be_modified(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);

        $this->actingAs($sysop)->post(route('sysop.operations.runtime-jobs.toggle'), [
            'job_key' => 'tracker.announce.integrity',
            'enabled' => false,
        ])->assertSessionHasErrors('runtime_jobs');

        $this->assertDatabaseMissing('site_settings', ['key' => 'runtime.jobs.tracker.announce.integrity.enabled', 'value' => 'false']);
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
            ->assertSee('Configured tasks: unavailable');
    }
}
