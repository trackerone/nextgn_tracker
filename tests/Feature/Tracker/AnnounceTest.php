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
        $infoHashHex = $this->makeInfoHashHex('torrent-invalid-passkey');

        Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
        ]);

        $params = [
            'info_hash' => hex2bin($infoHashHex),
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

        $infoHashHex = $this->makeInfoHashHex('torrent-started');
        $peerIdHex = $this->makePeerIdHex('peer-started');

        $torrent = Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
        ]);

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

        $infoHashHex = $this->makeInfoHashHex('torrent-banned');

        Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
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

        $infoHashHex = $this->makeInfoHashHex('torrent-unapproved');

        Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
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
        $infoHashHex = $this->makeInfoHashHex('torrent-low-ratio');
        $peerIdHex = $this->makePeerIdHex('low-ratio');

        $torrent = Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
        ]);

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
        $infoHashHex = $this->makeInfoHashHex('torrent-complete-once');
        $peerIdHex = $this->makePeerIdHex('complete-once');

        $torrent = Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
        ]);

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
        $infoHashHex = $this->makeInfoHashHex('torrent-stopped');
        $peerIdHex = $this->makePeerIdHex('stopped-peer');

        $torrent = Torrent::factory()->create([
            'info_hash' => hex2bin($infoHashHex),
        ]);

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

    private function announce(User $user, string $infoHashHex, array $overrides = []): TestResponse
    {
        $params = array_merge([
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

    private function makePeerIdHex(string $seed): string
    {
        return strtolower(
            bin2hex(substr(hash('sha1', $seed, true), 0, 20))
        );
    }

    private function makeInfoHashHex(string $seed): string
    {
        return strtolower(sha1($seed));
    }
}
