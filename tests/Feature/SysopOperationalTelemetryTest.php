<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Operations\OperationsAlerts;
use App\Services\Operations\OperationsProbe;
use App\Services\Operations\OperationsTelemetry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('returns real shallow readiness without disclosing dependency errors', function (): void {
    $this->get('/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Cache-Control', 'no-store');

    Cache::shouldReceive('store')->andThrow(new RuntimeException('secret: redis-auth-token'));

    $this->get('/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'degraded'])
        ->assertDontSee('redis-auth-token');
});

it('stores safe job heartbeats and distinguishes observed scheduler from definitions', function (): void {
    $telemetry = app(OperationsTelemetry::class);
    $probe = app(OperationsProbe::class);
    $before = collect($probe->collect()['checks'])->keyBy('id');
    expect($before['scheduler']['status'])->toBe('unknown');

    $telemetry->job('scheduler.tick', 'succeeded');
    $telemetry->job('tracker:prune-ghost-peers', 'started');
    $telemetry->job('tracker:prune-ghost-peers', 'failed', null, 'api_key=private-key');
    $after = collect($probe->collect()['checks'])->keyBy('id');

    expect($after['scheduler']['status'])->toBe('healthy');

    $row = DB::table('ops_job_status')->where('job_key', 'tracker:prune-ghost-peers')->first();
    expect($row->state)->toBe('failed')
        ->and((string) $row->last_error)->not->toContain('private-key');
});

it('treats file cache and sync queue correctly without pretending a queue worker is running', function (): void {
    config()->set('cache.default', 'array');
    config()->set('queue.default', 'sync');

    $checks = collect(app(OperationsProbe::class)->collect()['checks'])->keyBy('id');

    expect($checks['cache']['status'])->toBe('healthy')
        ->and($checks['queue']['status'])->toBe('not_applicable')
        ->and($checks['queue']['details'])->toContain('no worker required');
});

it('reports cache failures as degraded and can never report all green', function (): void {
    Cache::shouldReceive('store')->andThrow(new RuntimeException('cache offline'));
    $status = app(OperationsProbe::class)->collect();
    $checks = collect($status['checks'])->keyBy('id');

    expect($checks['cache']['status'])->toBe('degraded')
        ->and($status['overall'])->not->toBe('healthy');
});

it('keeps traffic metrics anonymous and detects a high tracker rejection rate', function (): void {
    $telemetry = app(OperationsTelemetry::class);
    $telemetry->count('announce_ok');
    for ($i = 0; $i < 12; $i++) {
        $telemetry->count('announce_rejected');
    }

    $status = app(OperationsProbe::class)->collect();
    $checks = collect($status['checks'])->keyBy('id');

    expect($status['counters']['announce_ok'])->toBe(1)
        ->and($status['counters']['announce_rejected'])->toBe(12)
        ->and($checks['tracker']['status'])->toBe('degraded');
});

it('deduplicates repeated incidents and allows sysop acknowledgement and recovery', function (): void {
    $alerts = app(OperationsAlerts::class);
    $checks = [['id' => 'tracker', 'label' => 'Tracker', 'status' => 'degraded', 'details' => 'High failures']];
    $alerts->reconcile($checks);
    $alerts->reconcile($checks);

    $row = DB::table('ops_alerts')->where('fingerprint', 'health:tracker')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('active')
        ->and(DB::table('ops_alerts')->count())->toBe(1);

    $member = User::factory()->create(['role' => User::ROLE_USER]);
    $this->actingAs($member)->post(route('sysop.operations.alerts.action'), [
        'alert_id' => $row->id, 'action' => 'acknowledge',
    ])->assertForbidden();

    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $this->actingAs($sysop)->post(route('sysop.operations.alerts.action'), [
        'alert_id' => $row->id, 'action' => 'acknowledge',
    ])->assertRedirect();

    expect(DB::table('ops_alerts')->where('id', $row->id)->value('status'))->toBe('acknowledged');

    $alerts->reconcile([['id' => 'tracker', 'label' => 'Tracker', 'status' => 'healthy', 'details' => 'Recovered']]);
    expect(DB::table('ops_alerts')->where('id', $row->id)->value('status'))->toBe('resolved');
});

it('writes durable periodic snapshot without a dependency on a Sysop visiting', function (): void {
    $this->artisan('ops:monitor')->assertExitCode(0);
    expect(DB::table('ops_snapshots')->count())->toBe(1);

    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $this->actingAs($sysop)->get(route('sysop.operations.index'))
        ->assertOk()
        ->assertSee('Control Center')
        ->assertSee('Tracker activity')
        ->assertSee('Jobs and scheduler heartbeat')
        ->assertSee('Incident inbox');
});

it('exposes incident log sources only to Sysop and no passkey data', function (): void {
    app(OperationsAlerts::class)->reconcile([
        ['id' => 'tracker', 'label' => 'Tracker', 'status' => 'critical', 'details' => 'No passkeys should be logged'],
    ]);

    $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))
        ->get('/sysop/logs?source=tracker')->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_SYSOP]))
        ->get('/sysop/logs?source=tracker&window=15m')
        ->assertOk()
        ->assertSee('Tracker needs attention')
        ->assertSee('Incident #');
});
