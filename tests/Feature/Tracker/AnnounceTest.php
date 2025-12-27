<?php

declare(strict_types=1);

namespace Tests\Feature\Tracker;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnnounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_started_event_registers_peer_and_returns_payload(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-started'),
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
            'info_hash' => $this->makeInfoHashHex('torrent-completed-updates'),
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
            'info_hash' => $this->makeInfoHashHex('torrent-stopped'),
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
            'info_hash' => $this->makeInfoHashHex('torrent-numwant'),
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

    public function test_invalid_passkey_returns_failure(): void
    {
        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-invalid-passkey'),
        ]);

        $params = [
            'info_hash' => strtolower((string) $torrent->info_hash),
            'peer_id' => bin2hex($this->makePeerId('invalid-passkey')),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $response = $this->get('/announce/not-a-real-passkey?'.$query);

        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'failure reason' => 'Invalid passkey.',
        ]);

        $this->assertSame($expected, $response->getContent());
    }

    public function test_banned_torrent_returns_failure(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-banned'),
            'is_banned' => true,
            'ban_reason' => 'DMCA',
        ]);

        $response = $this->announce($user, $torrent, $this->makePeerId('banned-torrent'));

        $response->assertOk();
        $this->assertStringContainsString('Torrent is banned', (string) $response->getContent());
    }

    public function test_unapproved_torrent_is_rejected_for_regular_users(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-unapproved'),
            'is_approved' => false,
            'status' => Torrent::STATUS_PENDING,
        ]);

        $response = $this->announce($user, $torrent, $this->makePeerId('unapproved'));

        $response->assertOk();
        $this->assertStringContainsString('Torrent is not approved yet', (string) $response->getContent());
    }

    public function test_low_ratio_user_blocked_on_started_event(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-low-ratio'),
        ]);

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 10,
            'downloaded' => 200,
        ]);

        $response = $this->announce($user, $torrent, $this->makePeerId('low-ratio'), [
            'event' => 'started',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('ratio is too low', (string) $response->getContent());
    }

    public function test_normal_ratio_user_is_allowed_to_start(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-ok-ratio'),
            'seeders' => 0,
            'leechers' => 0,
        ]);

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 500,
            'downloaded' => 200,
        ]);

        $response = $this->announce($user, $torrent, $this->makePeerId('ok-ratio'), [
            'event' => 'started',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('ratio is too low', (string) $response->getContent());
    }

    public function test_staff_can_bypass_ratio_and_approval_checks(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-staff-bypass'),
            'is_approved' => false,
            'status' => Torrent::STATUS_PENDING,
            'seeders' => 0,
            'leechers' => 0,
        ]);

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 10,
            'downloaded' => 200,
        ]);

        $response = $this->announce($user, $torrent, $this->makePeerId('staff-ok'), [
            'event' => 'started',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('ratio is too low', (string) $response->getContent());
        $this->assertStringNotContainsString('not approved', (string) $response->getContent());
    }

    public function test_user_torrent_stats_are_upserted_from_announce(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-user-torrents-upsert'),
        ]);

        $peerId = $this->makePeerId('stats-peer');

        $this->announce($user, $torrent, $peerId, [
            'uploaded' => 512,
            'downloaded' => 1024,
        ])->assertOk();

        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 512,
            'downloaded' => 1024,
        ]);

        $this->announce($user, $torrent, $peerId, [
            'uploaded' => 2048,
            'downloaded' => 4096,
        ])->assertOk();

        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 2048,
            'downloaded' => 4096,
        ]);
    }

    public function test_completed_event_sets_completed_at_once(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'info_hash' => $this->makeInfoHashHex('torrent-completed-at-once'),
        ]);

        $peerId = $this->makePeerId('snatch-peer');

        $this->announce($user, $torrent, $peerId, [
            'left' => 0,
            'event' => 'completed',
        ])->assertOk();

        $snatch = UserTorrent::query()->first();
        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);
        $firstCompletedAt = $snatch->completed_at;

        $this->travel(5)->minutes();

        $this->announce($user, $torrent, $peerId, [
            'left' => 0,
        ])->assertOk();

        $snatch->refresh();
        $this->assertNotNull($firstCompletedAt);
        $this->assertSame(
            $firstCompletedAt->toDateTimeString(),
            optional($snatch->completed_at)->toDateTimeString()
        );
    }

    private function announce(User $user, Torrent $torrent, string $peerId, array $overrides = []): TestResponse
    {
        $infoHash = (string) $torrent->info_hash;

        // Always transport as 40-hex in tests (stable and deterministic).
        if (strlen($infoHash) === 20) {
            $infoHash = bin2hex($infoHash);
        } else {
            $infoHash = strtolower($infoHash);
        }

        $params = array_merge([
            'info_hash' => $infoHash,
            'peer_id' => bin2hex($peerId),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ], $overrides);

        // Normalize if an override provides raw 20-byte values.
        if (isset($params['info_hash']) && is_string($params['info_hash']) && strlen($params['info_hash']) === 20) {
            $params['info_hash'] = bin2hex($params['info_hash']);
        }
        if (isset($params['peer_id']) && is_string($params['peer_id']) && strlen($params['peer_id']) === 20) {
            $params['peer_id'] = bin2hex($params['peer_id']);
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->get('/announce/'.$user->ensurePasskey().'?'.$query);
    }

    private function makePeerId(string $seed): string
    {
        return substr(hash('sha1', $seed, true), 0, 20);
    }

    private function makeInfoHashHex(string $seed): string
    {
        // 40-char lowercase hex
        return strtolower(sha1($seed));
    }
}
