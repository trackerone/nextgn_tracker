<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;

it('keeps the global header scrollable on mobile and compacts navigation', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

    $this->actingAs($sysop)
        ->get(route('sysop.logs.index'))
        ->assertOk()
        ->assertSee('relative z-40 border-b')
        ->assertSee('md:sticky md:top-0')
        ->assertSee('grid grid-cols-2 gap-2 sm:flex sm:flex-wrap')
        ->assertSee('sm:gap-4 sm:py-4');
});

it('wraps long audit event names and JSON values without horizontal page overflow', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $audit = AuditLog::query()->create([
        'user_id' => $sysop->id,
        'action' => 'sysop.runtime_job_state_changed_with_really_long_identifier',
        'metadata' => ['job_key' => str_repeat('long-identifier-', 20)],
    ]);

    $this->actingAs($sysop)
        ->get(route('admin.logs.audit.show', $audit))
        ->assertOk()
        ->assertSee('Back to Log Center')
        ->assertSee('break-words text-xl')
        ->assertSee('whitespace-pre-wrap break-all')
        ->assertSee('long-identifier-');
});

it('links sysops back to security logs and handles long security details', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $event = SecurityEvent::query()->create([
        'user_id' => $sysop->id,
        'event_type' => str_repeat('suspicious_access_', 8),
        'severity' => 'high',
        'message' => str_repeat('long-message-', 25),
        'context' => ['agent' => str_repeat('long-useragent-', 10)],
    ]);

    $this->actingAs($sysop)
        ->get(route('admin.logs.security.show', $event))
        ->assertOk()
        ->assertSee('Back to Log Center')
        ->assertSee('whitespace-pre-wrap break-all')
        ->assertSee('break-words text-xl');
});

it('keeps admin audit navigation for non-sysop log viewers', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_staff' => true]);
    $audit = AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'test.action']);

    $this->actingAs($admin)
        ->get(route('admin.logs.audit.show', $audit))
        ->assertOk()
        ->assertSee('Back to audit logs')
        ->assertDontSee('Back to Log Center');
});
