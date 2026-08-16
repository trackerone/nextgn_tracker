<?php

declare(strict_types=1);

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\Torrent;
use App\Tracker\Announce\AnnounceResponseBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

it('prunes expired peers and refreshes affected swarm totals', function (): void {
    $this->travelTo(Carbon::parse('2026-08-16 12:00:00'));
    config()->set('tracker.ghost_peer_timeout_minutes', 45);

    $torrent = Torrent::factory()->create([
        'seeders' => 2,
        'leechers' => 2,
    ]);

    $expiredSeeder = Peer::factory()->for($torrent)->create([
        'is_seeder' => true,
        'last_announce_at' => now()->subMinutes(46),
    ]);
    $expiredLeecher = Peer::factory()->for($torrent)->create([
        'is_seeder' => false,
        'last_announce_at' => now()->subHour(),
    ]);
    $activeSeeder = Peer::factory()->for($torrent)->create([
        'is_seeder' => true,
        'last_announce_at' => now()->subMinutes(44),
    ]);
    $activeLeecher = Peer::factory()->for($torrent)->create([
        'is_seeder' => false,
        'last_announce_at' => now()->subMinutes(5),
    ]);

    $exitCode = Artisan::call('tracker:prune-ghost-peers');

    expect($exitCode)->toBe(SymfonyCommand::SUCCESS)
        ->and(Artisan::output())->toContain('Pruned 2 ghost peer(s) across 1 torrent(s).')
        ->and(Peer::query()->whereKey($expiredSeeder->getKey())->exists())->toBeFalse()
        ->and(Peer::query()->whereKey($expiredLeecher->getKey())->exists())->toBeFalse()
        ->and(Peer::query()->whereKey($activeSeeder->getKey())->exists())->toBeTrue()
        ->and(Peer::query()->whereKey($activeLeecher->getKey())->exists())->toBeTrue();

    $torrent->refresh();

    expect($torrent->seeders)->toBe(1)
        ->and($torrent->leechers)->toBe(1);
});

it('treats a peer at the configured cutoff as expired', function (): void {
    $this->travelTo(Carbon::parse('2026-08-16 12:00:00'));
    config()->set('tracker.ghost_peer_timeout_minutes', 30);

    $torrent = Torrent::factory()->create();
    $expiredAtCutoff = Peer::factory()->for($torrent)->create([
        'last_announce_at' => now()->subMinutes(30),
    ]);
    $stillActive = Peer::factory()->for($torrent)->create([
        'last_announce_at' => now()->subMinutes(29)->subSeconds(59),
    ]);

    Artisan::call('tracker:prune-ghost-peers');

    expect(Peer::query()->whereKey($expiredAtCutoff->getKey())->exists())->toBeFalse()
        ->and(Peer::query()->whereKey($stillActive->getKey())->exists())->toBeTrue();
});

it('supports a dry run without deleting peers or changing swarm totals', function (): void {
    $this->travelTo(Carbon::parse('2026-08-16 12:00:00'));
    config()->set('tracker.ghost_peer_timeout_minutes', 45);

    $torrent = Torrent::factory()->create([
        'seeders' => 7,
        'leechers' => 3,
    ]);
    $expiredPeer = Peer::factory()->for($torrent)->create([
        'is_seeder' => true,
        'last_announce_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('tracker:prune-ghost-peers', ['--dry-run' => true]);

    expect($exitCode)->toBe(SymfonyCommand::SUCCESS)
        ->and(Artisan::output())->toContain('Dry run: found 1 ghost peer(s) across 1 torrent(s).')
        ->and(Peer::query()->whereKey($expiredPeer->getKey())->exists())->toBeTrue();

    $torrent->refresh();

    expect($torrent->seeders)->toBe(7)
        ->and($torrent->leechers)->toBe(3);
});

it('excludes expired peers from live swarm totals before physical cleanup', function (): void {
    $this->travelTo(Carbon::parse('2026-08-16 12:00:00'));
    config()->set('tracker.ghost_peer_timeout_minutes', 45);

    $torrent = Torrent::factory()->create();
    Peer::factory()->for($torrent)->create([
        'is_seeder' => true,
        'last_announce_at' => now()->subMinutes(46),
    ]);
    Peer::factory()->for($torrent)->create([
        'is_seeder' => false,
        'last_announce_at' => now()->subMinutes(5),
    ]);

    app(TorrentRepositoryInterface::class)->refreshPeerStats($torrent);
    $torrent->refresh();

    expect($torrent->seeders)->toBe(0)
        ->and($torrent->leechers)->toBe(1)
        ->and(Peer::query()->where('torrent_id', $torrent->getKey())->count())->toBe(2);
});

it('excludes expired peers from announce peer lists before physical cleanup', function (): void {
    $this->travelTo(Carbon::parse('2026-08-16 12:00:00'));
    config()->set('tracker.ghost_peer_timeout_minutes', 45);

    $torrent = Torrent::factory()->create();
    Peer::factory()->for($torrent)->create([
        'peer_id' => 'expired-peer-0000001',
        'ip' => '10.0.0.1',
        'port' => 6881,
        'is_seeder' => true,
        'last_announce_at' => now()->subMinutes(46),
    ]);
    Peer::factory()->for($torrent)->create([
        'peer_id' => 'active-peer-00000001',
        'ip' => '10.0.0.2',
        'port' => 6882,
        'is_seeder' => false,
        'last_announce_at' => now()->subMinutes(5),
    ]);

    $builder = app(AnnounceResponseBuilder::class);
    $result = $builder->successWithPeers(
        $torrent,
        'requester-peer-00001',
        50,
    );
    $duplicateResult = $builder->successWithoutPeers($torrent);

    expect($result->payload['peers'])->toBe([[
        'ip' => '10.0.0.2',
        'port' => 6882,
    ]])
        ->and($duplicateResult->payload['complete'])->toBe(0)
        ->and($duplicateResult->payload['incomplete'])->toBe(1);
});

it('rejects an unsafe timeout without deleting peers', function (): void {
    config()->set('tracker.ghost_peer_timeout_minutes', 0);

    $expiredPeer = Peer::factory()->create([
        'last_announce_at' => now()->subDay(),
    ]);

    $exitCode = Artisan::call('tracker:prune-ghost-peers');

    expect($exitCode)->toBe(SymfonyCommand::FAILURE)
        ->and(Artisan::output())->toContain('TRACKER_GHOST_TIMEOUT_MINUTES must be at least 1.')
        ->and(Peer::query()->whereKey($expiredPeer->getKey())->exists())->toBeTrue();
});

it('registers ghost peer cleanup on a ten minute schedule', function (): void {
    Artisan::call('schedule:list');

    expect(Artisan::output())
        ->toContain('*/10 * * * *')
        ->toContain('tracker:prune-ghost-peers');
});
