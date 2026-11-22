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
            ->assertSeeText('Alpha launch readiness')
            ->assertSeeText('browse/detail/upload smoke checks')
            ->assertSee('Background job settings')
            ->assertSee('Ghost Peer Cleanup')
            ->assertSee('Protected core task')
            ->assertSee('Disable optional job')
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

    public function test_local_deployment_requirements_are_informational_and_tracker_limit_is_real(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);
        config()->set('security.production_hardening_enabled', false);
        config()->set('security.api.require_nonce', true);
        config()->set('security.api.allow_legacy_keys', true);
        config()->set('tracker.scrape_max_info_hashes', 37);

        $health = app(\App\Services\Operations\OperationsHealthService::class)->collect();
        $cards = collect($health['cards'])->keyBy('group');

        $this->assertSame('info', $health['production_card']['status']);
        $this->assertSame([], $health['production_card']['next_actions']);
        $this->assertSame('info', $cards['Scheduler']['status']);
        $this->assertSame('info', $cards['Queue']['status']);
        $this->assertSame('info', $cards['Tracker']['status']);
        $this->assertContains('Maximum torrents per scrape: 37 (enforced by the scrape endpoint).', $cards['Tracker']['items']);
        $this->assertNotEmpty($health['production_checks']);
        $this->assertNotContains('Disable legacy API key mode (SECURITY_API_ALLOW_LEGACY_KEYS=false).', $health['warnings']);
    }

    public function test_cache_readback_failure_is_not_reported_as_healthy(): void
    {
        $store = \Mockery::mock();
        $store->shouldReceive('put')->once()->andReturn(true);
        $store->shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('store')->andReturn($store);

        $health = app(\App\Services\Operations\OperationsHealthService::class)->collect(activeWriteChecks: true);
        $cards = collect($health['cards'])->keyBy('group');

        $this->assertSame('warning', $cards['Cache/Redis']['status']);
    }
}
