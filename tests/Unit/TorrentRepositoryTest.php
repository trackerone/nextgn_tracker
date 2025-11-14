<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\Torrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorrentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_torrent_factory_creates_model(): void
    {
        $torrent = Torrent::factory()->create();

        $this->assertNotNull($torrent->getKey());
        $this->assertDatabaseHas('torrents', ['id' => $torrent->getKey()]);
    }

    public function test_paginate_visible_returns_only_visible_records(): void
    {
        Torrent::factory()->count(2)->create(['is_visible' => true]);
        Torrent::factory()->create(['is_visible' => false]);

        $repository = $this->app->make(TorrentRepositoryInterface::class);

        $result = $repository->paginateVisible();

        $this->assertCount(2, $result->items());
        $this->assertTrue(collect($result->items())->every(fn (Torrent $torrent) => $torrent->isVisible()));
    }

    public function test_increment_stats_updates_counters(): void
    {
        $repository = $this->app->make(TorrentRepositoryInterface::class);
        $torrent = Torrent::factory()->create([
            'seeders' => 1,
            'leechers' => 2,
            'completed' => 3,
        ]);

        $repository->incrementStats($torrent, [
            'seeders' => 4,
            'leechers' => 5,
            'completed' => 6,
        ]);

        $fresh = $torrent->fresh();

        $this->assertSame(5, $fresh->seeders);
        $this->assertSame(7, $fresh->leechers);
        $this->assertSame(9, $fresh->completed);
    }

    public function test_refresh_peer_stats_counts_peers(): void
    {
        $repository = $this->app->make(TorrentRepositoryInterface::class);
        $torrent = Torrent::factory()->create([
            'seeders' => 0,
            'leechers' => 0,
        ]);

        Peer::factory()->count(2)->create([
            'torrent_id' => $torrent->id,
            'is_seeder' => true,
        ]);

        Peer::factory()->create([
            'torrent_id' => $torrent->id,
            'is_seeder' => false,
        ]);

        $repository->refreshPeerStats($torrent);

        $this->assertSame(2, $torrent->seeders);
        $this->assertSame(1, $torrent->leechers);
    }
}
