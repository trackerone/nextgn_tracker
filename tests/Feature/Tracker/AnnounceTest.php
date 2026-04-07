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

    public function test_invalid_passkey_returns_failure(): void
    {
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-invalid-passkey');

        $params = [
            'info_hash' => $infoHashHex,
            'peer_id' => $this->makePeerIdHex('invalid-passkey'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $response = $this->get(sprintf('/announce/%s?%s', 'not-a-real-passkey', $query));
        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'failure reason' => 'Invalid passkey.',
        ]);

        $this->assertSame($expected, $response->getContent());
    }

    public function test_started_event_registers_peer_and_returns_payload(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-started');
        $peerIdHex = $this->makePeerIdHex('peer-started');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'left' => 100,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $this->assertStringContainsString(
            '8:intervali1800e',
            (string) $response->getContent()
        );
    }

    public function test_banned_torrent_returns_failure(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-banned', [
            'is_banned' => true,
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('banned'),
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Torrent is banned',
            (string) $response->getContent()
        );
    }

    public function test_unapproved_torrent_is_rejected_for_regular_users(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-unapproved', [
            'is_approved' => false,
            'status' => Torrent::STATUS_PENDING,
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('unapproved'),
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Torrent is not approved yet',
            (string) $response->getContent()
        );
    }

    public function test_low_ratio_user_blocked_on_started_event(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-low-ratio');
        $peerIdHex = $this->makePeerIdHex('low-ratio');

        UserTorrent::factory()
            ->for($user)
            ->for($torrent)
            ->create([
                'uploaded' => 10,
                'downloaded' => 200,
            ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 100,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'ratio is too low',
            (string) $response->getContent()
        );
    }

    public function test_completed_event_sets_completed_at_once(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-complete-once');
        $peerIdHex = $this->makePeerIdHex('complete-once');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $snatch = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);

        $firstCompletedAt = $snatch->completed_at;

        $this->travel(5)->minutes();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $snatch->refresh();

        $this->assertSame(
            $firstCompletedAt->toDateTimeString(),
            $snatch->completed_at->toDateTimeString()
        );
    }

    public function test_stopped_event_removes_peer_and_updates_stats(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stopped');
        $peerIdHex = $this->makePeerIdHex('stopped-peer');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'stopped',
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);
    }

    public function test_stopped_event_on_missing_peer_returns_success_without_creating_peer(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stopped-missing');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('missing-stopped-peer'),
            'event' => 'stopped',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('failure reason', (string) $response->getContent());
        $this->assertDatabaseCount('peers', 0);
        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
        ]);
    }

    public function test_announce_without_event_updates_existing_peer_and_left_zero_sets_seeder(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-no-event-update');
        $peerIdHex = $this->makePeerIdHex('peer-no-event');
        $peerIdBinary = hex2bin($peerIdHex);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 5,
            'downloaded' => 10,
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 50,
            'downloaded' => 100,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 50,
            'downloaded' => 100,
            'left' => 0,
            'is_seeder' => true,
        ]);

        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 50,
            'downloaded' => 100,
        ]);
    }

    public function test_completed_event_does_not_double_increment_torrent_completed_counter(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-completed-counter-once');
        $peerIdHex = $this->makePeerIdHex('peer-completed-counter');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 50,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $torrent->refresh();

        $this->assertSame(1, $torrent->completed);
    }

    public function test_malformed_info_hash_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->announce($user, 'abcd', [
            'info_hash' => 'short',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('info_hash must be exactly 20 bytes', (string) $response->getContent());
    }

    public function test_malformed_peer_id_is_rejected(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-malformed-peer-id');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => 'short-peer-id',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('peer_id must be exactly 20 bytes', (string) $response->getContent());
    }

    public function test_banned_client_is_rejected_with_expected_failure_reason(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-banned-client');

        $response = $this->withHeader('User-Agent', 'BannedClient/1.0')
            ->get(sprintf(
                '/announce/%s?%s',
                $user->ensurePasskey(),
                http_build_query([
                    'info_hash' => strtolower($infoHashHex),
                    'peer_id' => $this->makePeerIdHex('banned-client-peer'),
                    'port' => 6881,
                    'uploaded' => 0,
                    'downloaded' => 0,
                    'left' => 100,
                ], '', '&', PHP_QUERY_RFC3986)
            ));

        $response->assertOk();
        $this->assertStringContainsString('Client is banned', (string) $response->getContent());
    }

    public function test_numwant_above_limit_is_clamped_instead_of_rejected(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-clamp');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-peer'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        for ($i = 0; $i < 210; $i++) {
            $otherUser = User::factory()->create();
            $this->announce($otherUser, $infoHashHex, [
                'peer_id' => $this->makePeerIdHex('peer-'.$i),
                'event' => 'started',
                'left' => 0,
            ])->assertOk();
        }

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-peer'),
            'numwant' => 500,
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('must not be greater than 200', (string) $response->getContent());
    }

    public function test_numwant_zero_returns_success_with_no_peers(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-zero');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-zero'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $otherUser = User::factory()->create();
        $this->announce($otherUser, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-numwant-zero'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-zero'),
            'numwant' => 0,
        ]);

        $response->assertOk();

        $payload = $this->decodeAnnouncePayload($response);
        $this->assertSame([], $payload['peers']);
    }

    public function test_numwant_missing_uses_default_cap_of_fifty(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-default');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-default'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        for ($i = 0; $i < 75; $i++) {
            $otherUser = User::factory()->create();
            $this->announce($otherUser, $infoHashHex, [
                'peer_id' => $this->makePeerIdHex('peer-numwant-default-'.$i),
                'event' => 'started',
                'left' => 0,
            ])->assertOk();
        }

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-default'),
        ]);

        $response->assertOk();

        $payload = $this->decodeAnnouncePayload($response);
        $this->assertCount(50, $payload['peers']);
    }

    public function test_default_peer_list_order_is_deterministic_when_announces_share_timestamp(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-deterministic-order');
        $requesterPeerId = $this->makePeerIdHex('owner-deterministic-order');
        $firstPeerId = $this->makePeerIdHex('peer-a-deterministic-order');
        $secondPeerId = $this->makePeerIdHex('peer-b-deterministic-order');

        $this->travelTo(now()->startOfSecond());

        $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.1',
        ])->assertOk();

        $firstUser = User::factory()->create();
        $this->announce($firstUser, $infoHashHex, [
            'peer_id' => $firstPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.2',
        ])->assertOk();

        $secondUser = User::factory()->create();
        $this->announce($secondUser, $infoHashHex, [
            'peer_id' => $secondPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.3',
        ])->assertOk();

        $firstResponse = $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'numwant' => 2,
        ]);
        $secondResponse = $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'numwant' => 2,
        ]);

        $this->travelBack();

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $firstPayload = $this->decodeAnnouncePayload($firstResponse);
        $secondPayload = $this->decodeAnnouncePayload($secondResponse);

        $this->assertSame($firstPayload['peers'], $secondPayload['peers']);
    }

    public function test_stopped_after_completed_keeps_completion_idempotent_and_removes_peer(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stop-after-complete');
        $peerIdHex = $this->makePeerIdHex('peer-stop-after-complete');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 10,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'stopped',
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $snatch = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);

        $torrent->refresh();
        $this->assertSame(1, $torrent->completed);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAnnouncePayload(TestResponse $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = app(BencodeService::class)->decode((string) $response->getContent());

        return $payload;
    }

    private function announce(User $user, string $infoHashHex, array $overrides = []): TestResponse
    {
        $params = array_merge([
            // Send lowercase on wire to ensure robustness; repo normalizes to uppercase.
            'info_hash' => strtolower($infoHashHex),
            'peer_id' => $this->makePeerIdHex('default-peer'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ], $overrides);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->get(sprintf('/announce/%s?%s', $user->ensurePasskey(), $query));
    }

    private function createTorrentWithInfoHashHex(string $seed, array $overrides = []): array
    {
        // Repo expects uppercase lookup against torrents.info_hash
        $infoHashHex = $this->makeInfoHashHex($seed);

        $torrent = Torrent::factory()->create(array_merge([
            // Schema: string(40) unique
            'info_hash' => $infoHashHex,
        ], $overrides));

        return [$torrent, $infoHashHex];
    }

    private function makePeerIdHex(string $seed): string
    {
        return strtolower(
            bin2hex(substr(hash('sha1', $seed, true), 0, 20))
        );
    }

    private function makeInfoHashHex(string $seed): string
    {
        return strtoupper(sha1($seed));
    }
}
