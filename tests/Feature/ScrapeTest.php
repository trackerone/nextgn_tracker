<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_info_hash_returns_expected_stats_with_valid_passkey(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'seeders' => 12,
            'leechers' => 4,
            'completed' => 123,
        ]);

        $binaryHash = hex2bin($torrent->info_hash);
        $this->assertIsString($binaryHash);

        $response = $this->get('/scrape/'.$user->ensurePasskey().'?info_hash='.urlencode($binaryHash));

        $response->assertOk();

        $expected = [
            'files' => [
                $torrent->info_hash => [
                    'complete' => $torrent->seeders,
                    'incomplete' => $torrent->leechers,
                    'downloaded' => $torrent->completed,
                ],
            ],
        ];

        $this->assertSame(
            app(BencodeService::class)->encode($expected),
            $response->getContent(),
        );
    }

    public function test_multiple_info_hashes_are_returned_in_response(): void
    {
        $user = User::factory()->create();
        $first = Torrent::factory()->create([
            'seeders' => 5,
            'leechers' => 1,
            'completed' => 10,
        ]);
        $second = Torrent::factory()->create([
            'seeders' => 20,
            'leechers' => 8,
            'completed' => 55,
        ]);

        $firstBinary = hex2bin($first->info_hash);
        $secondBinary = hex2bin($second->info_hash);
        $this->assertIsString($firstBinary);
        $this->assertIsString($secondBinary);

        $query = 'info_hash='.rawurlencode($firstBinary).'&info_hash='.rawurlencode($secondBinary);

        $response = $this->get('/scrape/'.$user->ensurePasskey().'?'.$query);

        $response->assertOk();

        $expected = [
            'files' => [
                $first->info_hash => [
                    'complete' => $first->seeders,
                    'incomplete' => $first->leechers,
                    'downloaded' => $first->completed,
                ],
                $second->info_hash => [
                    'complete' => $second->seeders,
                    'incomplete' => $second->leechers,
                    'downloaded' => $second->completed,
                ],
            ],
        ];

        $this->assertSame(
            app(BencodeService::class)->encode($expected),
            $response->getContent(),
        );
    }

    public function test_unknown_info_hash_returns_zeroed_stats(): void
    {
        $user = User::factory()->create();
        $unknown = strtoupper(bin2hex(random_bytes(20)));
        $binary = hex2bin($unknown);
        $this->assertIsString($binary);
        $encoded = urlencode($binary);
        $requestedBinary = urldecode($encoded);
        $requestedHex = strtoupper(bin2hex($requestedBinary));

        $response = $this->get('/scrape/'.$user->ensurePasskey().'?info_hash='.$encoded);

        $response->assertOk();

        $expected = [
            'files' => [
                $requestedHex => [
                    'complete' => 0,
                    'incomplete' => 0,
                    'downloaded' => 0,
                ],
            ],
        ];

        $this->assertSame(
            app(BencodeService::class)->encode($expected),
            $response->getContent(),
        );
    }

    public function test_pending_torrent_scrape_returns_zero_stats_for_regular_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create([
            'seeders' => 9,
            'leechers' => 5,
            'completed' => 42,
        ]);

        $payload = $this->scrapePayload($user, $torrent);

        $this->assertSame([
            'complete' => 0,
            'downloaded' => 0,
            'incomplete' => 0,
        ], $payload['files'][$torrent->info_hash] ?? null);
    }

    public function test_banned_torrent_scrape_returns_zero_stats_for_regular_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->banned()->create([
            'seeders' => 9,
            'leechers' => 5,
            'completed' => 42,
        ]);

        $payload = $this->scrapePayload($user, $torrent);

        $this->assertSame([
            'complete' => 0,
            'downloaded' => 0,
            'incomplete' => 0,
        ], $payload['files'][$torrent->info_hash] ?? null);
    }

    public function test_rejected_and_soft_deleted_torrent_scrapes_return_zero_stats_for_regular_user(): void
    {
        $user = User::factory()->create();
        $rejected = Torrent::factory()->rejected()->create([
            'seeders' => 9,
            'leechers' => 5,
            'completed' => 42,
        ]);
        $softDeleted = Torrent::factory()->softDeleted()->create([
            'seeders' => 7,
            'leechers' => 3,
            'completed' => 21,
        ]);

        $rejectedPayload = $this->scrapePayload($user, $rejected);
        $softDeletedPayload = $this->scrapePayload($user, $softDeleted);

        $this->assertSame([
            'complete' => 0,
            'downloaded' => 0,
            'incomplete' => 0,
        ], $rejectedPayload['files'][$rejected->info_hash] ?? null);
        $this->assertSame([
            'complete' => 0,
            'downloaded' => 0,
            'incomplete' => 0,
        ], $softDeletedPayload['files'][$softDeleted->info_hash] ?? null);
    }

    public function test_hidden_torrent_scrape_returns_zero_stats_for_regular_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'is_visible' => false,
            'seeders' => 9,
            'leechers' => 5,
            'completed' => 42,
        ]);

        $payload = $this->scrapePayload($user, $torrent);

        $this->assertSame([
            'complete' => 0,
            'downloaded' => 0,
            'incomplete' => 0,
        ], $payload['files'][$torrent->info_hash] ?? null);
    }

    public function test_staff_can_scrape_non_public_torrent_stats(): void
    {
        $staff = User::factory()->staff()->create();
        $torrent = Torrent::factory()->unapproved()->create([
            'is_visible' => false,
            'seeders' => 9,
            'leechers' => 5,
            'completed' => 42,
        ]);

        $payload = $this->scrapePayload($staff, $torrent);

        $this->assertSame([
            'complete' => 9,
            'downloaded' => 42,
            'incomplete' => 5,
        ], $payload['files'][$torrent->info_hash] ?? null);
    }

    public function test_invalid_passkey_is_rejected_for_scrape(): void
    {
        $response = $this->get('/scrape/invalid-passkey');

        $response->assertOk();

        $this->assertSame(
            app(BencodeService::class)->encode(['failure reason' => 'Invalid passkey.']),
            $response->getContent(),
        );
    }

    public function test_scrape_payload_exposes_only_stats(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Sensitive torrent title',
            'description' => 'Sensitive metadata',
            'seeders' => 7,
            'leechers' => 3,
            'completed' => 11,
        ]);

        $binaryHash = hex2bin($torrent->info_hash);
        $this->assertIsString($binaryHash);

        $response = $this->get('/scrape/'.$user->ensurePasskey().'?info_hash='.urlencode($binaryHash));

        $response->assertOk();

        $payload = app(BencodeService::class)->decode((string) $response->getContent());

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('files', $payload);
        $this->assertArrayNotHasKey('name', $payload);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('category', $payload);
        $this->assertArrayNotHasKey('uploader', $payload);
        $this->assertArrayNotHasKey('release', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function scrapePayload(User $user, Torrent $torrent): array
    {
        $binaryHash = hex2bin($torrent->info_hash);
        $this->assertIsString($binaryHash);

        $response = $this->get('/scrape/'.$user->ensurePasskey().'?info_hash='.urlencode($binaryHash));
        $response->assertOk();

        $decoded = app(BencodeService::class)->decode((string) $response->getContent());
        $this->assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
