<?php

declare(strict_types=1);

namespace Tests\Feature\Tracker;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_started_event_registers_peer_and_returns_payload(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'seeders' => 0,
            'leechers' => 0,
        ]);

        $peerId = $this->makePeerId('peer-started');

        $response = $this->announce($user, $torrent, $peerId);

        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'complete' => 0,
            'incomplete' => 1,
            'interval' => 1800,
            'peers' => [],
        ]);

        $this->assertSame($expected, $response->getContent());

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $peerId,
            'is_seeder' => false,
        ]);

        $torrent->refresh();

        $this->assertSame(0, $torrent->seeders);
        $this->assertSame(1, $torrent->leechers);
    }

    public function test_completed_event_updates_stats_and_returns_other_peers(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'seeders' => 0,
            'leechers' => 0,
            'completed' => 0,
        ]);

        $firstPeer = $this->makePeerId('first-peer');
        $secondPeer = $this->makePeerId('second-peer');

        $this->announce($firstUser, $torrent, $firstPeer)->assertOk();

        $response = $this->announce($secondUser, $torrent, $secondPeer, [
            'left' => 0,
            'event' => 'completed',
        ]);

        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'complete' => 1,
            'incomplete' => 1,
            'interval' => 1800,
            'peers' => [
                [
                    'ip' => '127.0.0.1',
                    'port' => 6881,
                ],
            ],
        ]);

        $this->assertSame($expected, $response->getContent());

        $torrent->refresh();

        $this->assertSame(1, $torrent->seeders);
        $this->assertSame(1, $torrent->leechers);
        $this->assertSame(1, $torrent->completed);

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $secondPeer,
            'is_seeder' => true,
        ]);
    }

    public function test_stopped_event_removes_peer_and_updates_stats(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'seeders' => 0,
            'leechers' => 0,
        ]);

        $peerId = $this->makePeerId('stopped-peer');

        $this->announce($user, $torrent, $peerId)->assertOk();

        $response = $this->announce($user, $torrent, $peerId, [
            'event' => 'stopped',
        ]);

        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'complete' => 0,
            'incomplete' => 0,
            'interval' => 1800,
            'peers' => [],
        ]);

        $this->assertSame($expected, $response->getContent());

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $peerId,
        ]);

        $torrent->refresh();

        $this->assertSame(0, $torrent->seeders);
        $this->assertSame(0, $torrent->leechers);
    }

    public function test_numwant_limits_peer_list(): void
    {
        $torrent = Torrent::factory()->create([
            'seeders' => 0,
            'leechers' => 0,
        ]);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $thirdUser = User::factory()->create();

        $firstPeer = $this->makePeerId('peer-one');
        $secondPeer = $this->makePeerId('peer-two');
        $thirdPeer = $this->makePeerId('peer-three');

        $this->announce($firstUser, $torrent, $firstPeer)->assertOk();
        $this->announce($secondUser, $torrent, $secondPeer)->assertOk();

        $response = $this->announce($thirdUser, $torrent, $thirdPeer, [
            'numwant' => 1,
        ]);

        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'complete' => 0,
            'incomplete' => 3,
            'interval' => 1800,
            'peers' => [
                [
                    'ip' => '127.0.0.1',
                    'port' => 6881,
                ],
            ],
        ]);

        $this->assertSame($expected, $response->getContent());
    }

    private function announce(User $user, Torrent $torrent, string $peerId, array $overrides = [])
    {
        $infoHashBinary = hex2bin($torrent->info_hash);
        $this->assertIsString($infoHashBinary);

        $params = array_merge([
            'info_hash' => $infoHashBinary,
            'peer_id' => $peerId,
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ], $overrides);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->actingAs($user)->get('/announce?'.$query);
    }

    private function makePeerId(string $prefix): string
    {
        return substr(str_pad($prefix, 20, 'x'), 0, 20);
    }
}
