<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Operations\SensitiveLogRedactor;
use Illuminate\Support\Facades\DB;

it('requires a sysop role for the log center', function (): void {
    $this->get('/sysop/logs')->assertRedirect(route('login'));

    foreach ([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/sysop/logs')
            ->assertForbidden();
    }

    $this->actingAs(User::factory()->create(['role' => User::ROLE_SYSOP]))
        ->get('/sysop/logs')
        ->assertOk()
        ->assertSee('Log Center')
        ->assertSee('Failed jobs');
});

it('filters audit records without showing unrelated records', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $log = AuditLog::query()->create([
        'user_id' => $sysop->id,
        'action' => 'torrent.approved',
        'metadata' => ['api_key' => 'secret-never-visible'],
    ]);
    AuditLog::query()->create(['user_id' => $sysop->id, 'action' => 'torrent.rejected']);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=audit&q=approved')
        ->assertOk()
        ->assertSee('torrent.approved')
        ->assertDontSee('torrent.rejected');

    $this->get(route('admin.logs.audit.show', $log))
        ->assertOk()
        ->assertSee('[REDACTED]')
        ->assertDontSee('secret-never-visible');
});

it('shows security event severity and redacts credentials on detail pages', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $event = SecurityEvent::query()->create([
        'user_id' => $sysop->id,
        'event_type' => 'invalid_passkey',
        'severity' => 'high',
        'message' => 'denied token=abcsecret',
        'context' => ['password' => 'ultra-secret-password'],
    ]);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=security&level=error')
        ->assertOk()
        ->assertSee('invalid_passkey')
        ->assertSee('[REDACTED]')
        ->assertDontSee('abcsecret');

    $this->get(route('admin.logs.security.show', $event))
        ->assertOk()
        ->assertDontSee('ultra-secret-password');
});

it('shows failed jobs without serialized payload or unredacted exception secrets', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

    DB::table('failed_jobs')->insert([
        'uuid' => 'b76179bc-3d04-4025-a6e2-01c711b98c01',
        'connection' => 'database',
        'queue' => 'metadata',
        'payload' => '{"token":"do-not-print-payload"}',
        'exception' => "Job failed; api_key=do-not-print-key\ntrace with secrets",
        'failed_at' => now(),
    ]);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=jobs')
        ->assertOk()
        ->assertSee('metadata')
        ->assertSee('[REDACTED]')
        ->assertDontSee('do-not-print-payload')
        ->assertDontSee('do-not-print-key')
        ->assertDontSee('trace with secrets');
});

it('rejects unsupported log file parameters and never accepts paths', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

    $this->actingAs($sysop)
        ->get('/sysop/logs?source=../../.env')
        ->assertSessionHasErrors(['source']);
});

it('reads recent Laravel entries without exposing tokenized tracker URLs or stack traces', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $path = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    $existing = file_exists($path) ? file_get_contents($path) : null;

    try {
        file_put_contents(
            $path,
            '['.now()->format('Y-m-d H:i:s')."] local.ERROR: Tracker rejected /announce/secretpasskey123 token=hidden-secret\n".
            "#0 secret-stack-trace\n",
        );
        $this->actingAs($sysop)
            ->get('/sysop/logs?source=application&level=error')
            ->assertOk()
            ->assertSee('Tracker rejected')
            ->assertSee('[REDACTED]')
            ->assertDontSee('secretpasskey123')
            ->assertDontSee('hidden-secret')
            ->assertDontSee('secret-stack-trace');
    } finally {
        if ($existing === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $existing);
        }
    }
});

it('redacts nested structured fields and common bearer and tracker URL secrets', function (): void {
    $redactor = new SensitiveLogRedactor;
    $data = $redactor->value([
        'api_key' => 'private-value',
        'nested' => ['refresh_token' => 'private-refresh', 'message' => 'password=private-password'],
    ]);

    expect($data['api_key'])->toBe('[REDACTED]')
        ->and($data['nested']['refresh_token'])->toBe('[REDACTED]')
        ->and($data['nested']['message'])->not->toContain('private-password');

    $line = $redactor->text('Bearer bearer-token.123 /scrape/abcdefgh12345678 ?token=query-secret');
    expect($line)->not->toContain('bearer-token')
        ->not->toContain('abcdefgh12345678')
        ->not->toContain('query-secret');
});
