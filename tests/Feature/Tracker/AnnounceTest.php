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
            'interval' => 900,
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
            'interval' => 900,
            'peers' => [
                [
                    'peer id' => $firstPeer,
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
