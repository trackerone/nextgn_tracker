<?php

declare(strict_types=1);

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies security event access for guests', function (): void {
    get('/admin/logs/security')->assertStatus(302);
});

it('denies security event access for non-staff users', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    actingAs($user);

    get('/admin/logs/security')->assertForbidden();
});

it('allows log viewers to filter and view security events', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $eventVisible = SecurityEvent::query()->create([
        'user_id' => $admin->getKey(),
        'ip_address' => '3.3.3.3',
        'user_agent' => 'ClientA',
        'event_type' => 'tracker.client_banned',
        'severity' => 'high',
        'message' => 'Banned client attempted announce',
        'context' => ['peer_id' => '123'],
    ]);
    $eventVisible->forceFill([
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ])->save();

    $eventHidden = SecurityEvent::query()->create([
        'user_id' => null,
        'ip_address' => '4.4.4.4',
        'user_agent' => 'ClientB',
        'event_type' => 'tracker.invalid_passkey',
        'severity' => 'low',
        'message' => 'Ignored',
    ]);
    $eventHidden->forceFill([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ])->save();

    actingAs($admin);

    $response = get('/admin/logs/security?severity=high&event_type=tracker.client_banned&from='.now()->subDay()->format('Y-m-d\TH:i'));

    $response->assertOk()
        ->assertSee('tracker.client_banned')
        ->assertDontSee('tracker.invalid_passkey');

    get(route('admin.logs.security.show', $eventVisible))
        ->assertOk()
        ->assertSee('Banned client attempted announce');
});
