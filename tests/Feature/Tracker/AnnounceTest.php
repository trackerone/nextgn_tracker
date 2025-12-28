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

        $torrent = Torrent::factory()->create([
            'info_hash' => $infoHashHex,
        ]);

        $params = [
            'info_hash' => $infoHashHex,
            'peer_id' => $this->makePeerIdHex('invalid-passkey'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $response = $this->get('/announce/not-a-real-passkey?' . $query);
        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'failure reason' => 'Invalid passkey.',
        ]);

        $this->assertSame($expected, $response->getContent());
    }

    public function test_started_event_returns_success_payload(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        $infoHashHex = $this->makeInfoHashHex('torrent-started');

        $torrent = Torrent::factory()->create([
            'info_hash' => $infoHashHex,
            'seeders' => 0,
            'leechers' => 0,
            'completed' => 0,
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-started'),
            'left' => 100,
        ]);

        $response->assertOk();

        // Stable signal: successful payload includes "interval"
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

        $torrent = Torrent::factory()->create([
            'info_hash' => $infoHashHex,
            'is_banned' => true,
            'ban_reason' => 'DMCA',
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('banned-torrent'),
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

        $torrent = Torrent::factory()->create([
            'info_hash' => $infoHashHex,
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

        $torrent = Torrent::factory()->create([
            'info_hash' => $infoHashHex,
        ]);

        // Set user ratio low
        UserTorrent::factory()
            ->for($user)
            ->for($torrent)
            ->create([
                'uploaded' => 10,
                'downloaded' => 200,
            ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('low-ratio'),
            'event' => 'started',
            'left' => 100,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'ratio is too low',
            (string) $response->getContent()
        );
    }

    /**
     * Stable announce helper: always uses deterministic 40-hex transport
     * for info_hash and peer_id.
     */
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

        // Ensure deterministic casing
        if (isset($params['info_hash'])) {
            $params['info_hash'] = strtolower((string) $params['info_hash']);
        }
        if (isset($params['peer_id'])) {
            $params['peer_id'] = strtolower((string) $params['peer_id']);
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->get(
            '/announce/' . $user->ensurePasskey() . '?' . $query
        );
    }

    private function makePeerIdHex(string $seed): string
    {
        // 40-char hex (20 bytes)
        return strtolower(
            bin2hex(substr(hash('sha1', $seed, true), 0, 20))
        );
    }

    private function makeInfoHashHex(string $seed): string
    {
        // 40-char lowercase hex
        return strtolower(sha1($seed));
    }
}
