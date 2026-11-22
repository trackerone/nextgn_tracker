<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Operations\SensitiveLogRedactor;

it('opens the sysop log center on a source-aware overview', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $event = SecurityEvent::query()->create([
        'user_id' => $sysop->id,
        'event_type' => 'tracker.invalid_passkey',
        'severity' => 'high',
        'message' => 'Invalid passkey used during announce attempt',
    ]);

    $this->actingAs($sysop)
        ->get('/sysop/logs')
        ->assertOk()
        ->assertSee('Recent high-priority recorded events')
        ->assertSee('tracker.invalid_passkey')
        ->assertViewHas('overviewCounts', static fn (array $counts): bool => $counts['security'] !== null && $counts['security'] >= 1);
});

it('uses a single incidents tab with a component filter and retains common filters', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=alerts&component=tracker&window=7d')
        ->assertOk()
        ->assertSee('All incidents')
        ->assertSee('Infrastructure')
        ->assertSee('Incidents coverage:')
        ->assertSee('source=security', false)
        ->assertSee('window=7d', false);
});

it('preserves the sysop investigation context through security details', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $event = SecurityEvent::query()->create([
        'user_id' => $sysop->id,
        'event_type' => 'tracker.invalid_passkey',
        'severity' => 'high',
        'message' => 'Blocked announce request',
    ]);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=security&window=7d&level=error&q=blocked')
        ->assertOk()
        ->assertSee('event-security-'.$event->id, false)
        ->assertSee('Blocked announce request');

    $this->get(route('admin.logs.security.show', [
        'event' => $event, 'source' => 'security', 'window' => '7d',
        'level' => 'error', 'q' => 'blocked', 'page' => 2,
        'focus' => 'event-security-'.$event->id,
    ]))
        ->assertOk()
        ->assertSee('Tracker Invalid Passkey')
        ->assertSee('Technical details')
        ->assertSee('window=7d', false)
        ->assertSee('q=blocked', false)
        ->assertSee('page=2', false)
        ->assertSee('#event-security-'.$event->id, false);
});

it('preserves audit context and makes technical metadata secondary', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $log = AuditLog::query()->create([
        'user_id' => $sysop->id,
        'action' => 'sysop.runtime_job_state_changed',
        'metadata' => ['api_key' => 'never-expose-this'],
    ]);

    $this->actingAs($sysop)
        ->get(route('admin.logs.audit.show', [
            'log' => $log, 'source' => 'audit', 'window' => '30d',
            'q' => 'runtime', 'focus' => 'event-audit-'.$log->id,
        ]))
        ->assertOk()
        ->assertSee('Sysop Runtime Job State Changed')
        ->assertSee('Technical details')
        ->assertSee('window=30d', false)
        ->assertSee('#event-audit-'.$log->id, false)
        ->assertDontSee('never-expose-this');
});

it('does not expose path-based credentials in log details', function (): void {
    $redactor = new SensitiveLogRedactor;
    $line = $redactor->text(
        'Request /passkey/reusable-secret-value and %2Fannounce%2Fsome-secret-token from tracker'
    );

    expect($line)->not->toContain('reusable-secret-value')
        ->not->toContain('some-secret-token')
        ->toContain('[REDACTED]');
});
