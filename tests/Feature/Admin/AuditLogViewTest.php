<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies audit log access for guests', function (): void {
    get('/admin/logs/audit')->assertStatus(302);
});

it('denies audit log access for non-staff users', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    actingAs($user);

    get('/admin/logs/audit')->assertForbidden();
});

it('allows log viewers to filter and view audit logs', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $logVisible = AuditLog::query()->create([
        'user_id' => $admin->getKey(),
        'ip_address' => '1.1.1.1',
        'user_agent' => 'TestAgent',
        'action' => 'torrent.approved',
        'target_type' => User::class,
        'target_id' => $admin->getKey(),
        'metadata' => ['foo' => 'bar'],
    ]);
    $logVisible->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $logHidden = AuditLog::query()->create([
        'user_id' => null,
        'ip_address' => '2.2.2.2',
        'user_agent' => 'Hidden',
        'action' => 'torrent.rejected',
        'target_type' => User::class,
        'target_id' => $admin->getKey(),
    ]);
    $logHidden->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->save();

    actingAs($admin);

    $response = get('/admin/logs/audit?user_id='.$admin->getKey().'&action=torrent.approved&from='.now()->subDays(2)->format('Y-m-d\TH:i'));

    $response->assertOk()
        ->assertSee('torrent.approved')
        ->assertDontSee('torrent.rejected');

    get(route('admin.logs.audit.show', $logVisible))
        ->assertOk()
        ->assertSee('Metadata');
});
