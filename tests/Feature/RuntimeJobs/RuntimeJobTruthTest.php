<?php

declare(strict_types=1);

namespace Tests\Feature\RuntimeJobs;

use App\Models\User;
use App\Services\Operations\OperationsTelemetry;
use App\Services\Operations\RuntimeJobRegistry;
use App\Services\Settings\SiteSettingsRepository;
use Tests\TestCase;

final class RuntimeJobTruthTest extends TestCase
{
    public function test_unimplemented_jobs_are_not_configurable_or_reported_as_executing(): void
    {
        $jobs = collect(app(RuntimeJobRegistry::class)->all())->keyBy('key');

        foreach (['metadata.refresh', 'cache.warm', 'health.snapshot'] as $key) {
            self::assertSame('placeholder', $jobs[$key]['implementation']);
            self::assertFalse($jobs[$key]['can_toggle']);
            self::assertNull($jobs[$key]['last_success_at']);
            self::assertFalse(app(RuntimeJobRegistry::class)->update($key, false));
        }

        $this->assertDatabaseMissing('site_settings', ['key' => 'runtime.jobs.metadata.refresh.enabled']);
    }

    public function test_sysop_cannot_enable_placeholder_but_can_still_control_manual_digest(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)->post(route('sysop.operations.runtime-jobs.toggle'), [
            'job_key' => 'metadata.refresh',
            'enabled' => false,
        ])->assertRedirect(route('sysop.operations.index'))
            ->assertSessionHasErrors('runtime_jobs');

        $this->assertDatabaseMissing('site_settings', ['key' => 'runtime.jobs.metadata.refresh.enabled']);

        $this->actingAs($sysop)->post(route('sysop.operations.runtime-jobs.toggle'), [
            'job_key' => 'notification.digest',
            'enabled' => false,
        ])->assertRedirect(route('sysop.operations.index'));

        $this->assertDatabaseHas('site_settings', [
            'key' => 'runtime.jobs.notification.digest.enabled',
            'value' => 'false',
        ]);
    }

    public function test_critical_runtime_cannot_be_disabled_by_accidental_persisted_override(): void
    {
        app(SiteSettingsRepository::class)->set('runtime.jobs.tracker.peer.cleanup.enabled', false, 'bool');

        $registry = app(RuntimeJobRegistry::class);
        self::assertTrue($registry->isEnabled('tracker.peer.cleanup'));
        self::assertFalse($registry->update('tracker.peer.cleanup', false));
    }

    public function test_scheduler_definition_is_distinct_from_observed_success(): void
    {
        $registry = app(RuntimeJobRegistry::class);
        $jobs = collect($registry->all())->keyBy('key');

        self::assertSame('scheduled', $jobs['tracker.peer.cleanup']['implementation']);
        self::assertTrue($jobs['tracker.peer.cleanup']['schedule_registered']);
        self::assertNull($jobs['tracker.peer.cleanup']['last_success_at']);
        self::assertSame('manual', $jobs['notification.digest']['implementation']);
        self::assertTrue($jobs['notification.digest']['can_toggle']);
        self::assertNull($jobs['notification.digest']['schedule_registered']);

        app(OperationsTelemetry::class)->job('tracker:prune-ghost-peers', 'succeeded');
        $jobs = collect($registry->all())->keyBy('key');

        self::assertNotNull($jobs['tracker.peer.cleanup']['last_success_at']);
        self::assertNull($jobs['tracker.peer.cleanup']['last_failed_at']);
    }
}
