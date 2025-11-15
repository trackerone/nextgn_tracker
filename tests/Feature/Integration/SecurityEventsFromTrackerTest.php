<?php

declare(strict_types=1);

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs invalid passkey events during announce attempts', function (): void {
    get('/announce/invalid-passkey')->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.invalid_passkey')->count())->toBe(1);
});

it('logs banned clients attempting to announce', function (): void {
    $user = User::factory()->create([
        'passkey' => str_repeat('a', 64),
    ]);

    $query = http_build_query([
        'info_hash' => str_repeat('01', 20),
        'peer_id' => 'FakeClient1234567890',
        'port' => 6881,
        'uploaded' => 0,
        'downloaded' => 0,
        'left' => 1,
    ]);

    get('/announce/'.$user->passkey.'?'.$query)->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.client_banned')->count())->toBe(1);
});

it('logs rate limit violations for repeated announces', function (): void {
    $user = User::factory()->create([
        'passkey' => str_repeat('b', 64),
        'last_announce_at' => now(),
    ]);

    $query = http_build_query([
        'info_hash' => str_repeat('02', 20),
        'peer_id' => '-UT3520-1234567890AB',
        'port' => 6881,
        'uploaded' => 0,
        'downloaded' => 0,
        'left' => 1,
    ]);

    get('/announce/'.$user->passkey.'?'.$query)->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.rate_limited')->count())->toBe(1);
});
