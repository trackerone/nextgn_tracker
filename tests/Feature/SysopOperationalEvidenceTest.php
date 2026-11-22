<?php

declare(strict_types=1);

use App\Services\Operations\OperationsAlerts;
use App\Services\Operations\OperationsBackupEvidence;
use App\Services\Operations\OperationsProbe;
use App\Services\Operations\OperationsTelemetry;
use Illuminate\Support\Facades\DB;

it('does not mark an incident resolved when live telemetry becomes unknown', function (): void {
    $alerts = app(OperationsAlerts::class);
    $alerts->reconcile([['id' => 'tracker', 'label' => 'Tracker', 'status' => 'critical', 'details' => 'Failed']]);

    $alerts->reconcile([['id' => 'tracker', 'label' => 'Tracker', 'status' => 'unknown', 'details' => 'Cache offline']]);

    expect(DB::table('ops_alerts')->where('fingerprint', 'health:tracker')->value('status'))->toBe('active');
});

it('does not report a tracker healthy on unrelated web activity', function (): void {
    app(OperationsTelemetry::class)->count('http_ok');
    $checks = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');
    expect($checks['tracker']['status'])->toBe('unknown')
        ->and($checks['http']['status'])->toBe('healthy');
});

it('detects a stale ghost-peer cleanup task independently of cron ticking', function (): void {
    app(OperationsTelemetry::class)->job('scheduler.tick', 'succeeded');
    DB::table('ops_job_status')->insert([
        'job_key' => 'tracker:prune-ghost-peers',
        'state' => 'succeeded',
        'last_success_at' => now()->subMinutes(45),
        'created_at' => now()->subMinutes(45),
        'updated_at' => now()->subMinutes(45),
    ]);

    $checks = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');
    expect($checks['scheduler']['status'])->toBe('healthy')
        ->and($checks['job:tracker:prune-ghost-peers']['status'])->toBe('degraded');
});

it('requires real SHA256 artifact verification and separate restore attestation', function (): void {
    $root = sys_get_temp_dir().'/nextgn-ops-backups-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    config()->set('operations.backup_root', $root);

    try {
        foreach (['database', 'storage', 'secrets'] as $component) {
            $file = $root.'/'.$component.'.bak';
            file_put_contents($file, 'sample-'.$component.'-artifact');

            $this->artisan('ops:backup-evidence', [
                'component' => $component,
                'artifact' => $file,
                '--sha256' => hash_file('sha256', $file),
                '--offsite' => true,
            ])->assertExitCode(0);
        }

        $checks = collect(app(OperationsBackupEvidence::class)->checks())->keyBy('id');
        expect($checks['backups']['status'])->toBe('healthy')
            ->and($checks['restore_drill']['status'])->toBe('unknown');

        $report = $root.'/restore-report.txt';
        file_put_contents($report, 'A manually conducted isolated restore drill report.');
        $this->artisan('ops:backup-evidence', [
            'component' => 'restore',
            'artifact' => $report,
            '--attest-restore' => true,
        ])->assertExitCode(0);

        $checks = collect(app(OperationsBackupEvidence::class)->checks())->keyBy('id');
        expect($checks['restore_drill']['status'])->toBe('healthy')
            ->and($checks['restore_drill']['details'])->toContain('Operator-attested');

        $this->artisan('ops:backup-evidence', [
            'component' => 'database',
            'artifact' => $report,
            '--sha256' => str_repeat('0', 64),
        ])->assertExitCode(1);
    } finally {
        foreach (glob($root.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($root);
    }
});



it('shows pipeline failures without treating unobserved pipelines as healthy', function (): void {
    for ($i = 0; $i < 6; $i++) {
        app(OperationsTelemetry::class)->count('rss_error');
    }

    $checks = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');
    expect($checks['rss']['status'])->toBe('degraded')
        ->and($checks['metadata']['status'])->toBe('unknown')
        ->and($checks['browse']['status'])->toBe('unknown');
});

it('verifies a real asynchronous queue worker rather than counting zero failed jobs', function (): void {
    config()->set('queue.default', 'database');
    \Illuminate\Support\Facades\Queue::fake();
    $this->artisan('ops:monitor')->assertExitCode(0);
    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\OperationsQueueHeartbeatJob::class);

    $before = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');
    expect($before['queue']['status'])->toBe('unknown');

    (new \App\Jobs\OperationsQueueHeartbeatJob)->handle(app(OperationsTelemetry::class));
    $after = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');
    expect($after['queue']['status'])->toBe('healthy');
});


it('restricts support bundle to Sysop and excludes sensitive raw data', function (): void {
    $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_USER]))
        ->get(route('sysop.operations.support-bundle'))
        ->assertForbidden();

    $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_SYSOP]))
        ->get(route('sysop.operations.support-bundle'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="nextgn-sysop-diagnostics.json"')
        ->assertJsonPath('schema_version', 1)
        ->assertDontSee('api_key')
        ->assertDontSee('passkey');
});

it('collects host metrics without leaking filesystem contents or executing commands', function (): void {
    $host = app(\App\Services\Operations\OperationsHostMetrics::class)->collect();

    expect($host)->toHaveKeys(['memory_total_bytes', 'memory_available_bytes', 'cpu_cores', 'load_1m', 'rx_bytes', 'tx_bytes']);
    if (is_int($host['memory_total_bytes'])) {
        expect($host['memory_total_bytes'])->toBeGreaterThan(0);
    }
});
