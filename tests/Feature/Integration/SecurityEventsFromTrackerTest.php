<?php

declare(strict_types=1);

use App\Models\SecurityEvent;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs invalid passkey events during announce attempts', function (): void {
    $this->get('/announce/invalid-passkey')->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.invalid_passkey')->count())->toBe(1);
});

it('logs banned clients attempting to announce', function (): void {
    $user = User::factory()->create();

    Torrent::factory()->create();

    $query = http_build_query([
        'info_hash' => str_repeat('a', 20),
        'peer_id' => str_repeat('b', 20),
        'port' => 6881,
        'uploaded' => 0,
        'downloaded' => 0,
        'left' => 1,
    ]);

    $this->withHeader('User-Agent', 'BannedClient/1.0')
        ->get('/announce/'.$user->passkey.'?'.$query)
        ->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.client_banned')->count())->toBe(1);
});

it('logs rate limit violations for repeated announces', function (): void {
    $user = User::factory()->create();

    Torrent::factory()->create();

    $query = http_build_query([
        'info_hash' => str_repeat('a', 20),
        'peer_id' => str_repeat('b', 20),
        'port' => 6881,
        'uploaded' => 0,
        'downloaded' => 0,
        'left' => 1,
    ]);

    // First announce: allowed
    $this->get('/announce/'.$user->passkey.'?'.$query)->assertOk();

    // Second announce within rate-window: should be rate-limited + logged
    $this->get('/announce/'.$user->passkey.'?'.$query)->assertOk();

    expect(SecurityEvent::where('event_type', 'tracker.rate_limited')->count())->toBe(1);
});
