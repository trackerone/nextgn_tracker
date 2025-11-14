<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_info_hash_returns_expected_stats(): void
    {
        $torrent = Torrent::factory()->create([
            'seeders' => 12,
            'leechers' => 4,
            'completed' => 123,
        ]);

        $binaryHash = hex2bin($torrent->info_hash);
        $this->assertIsString($binaryHash);

        $response = $this->get('/scrape?info_hash='.urlencode($binaryHash));

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
            $response->getContent()
        );
    }

    public function test_multiple_info_hashes_are_returned_in_response(): void
    {
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

        $query = http_build_query([
            'info_hash' => [$firstBinary, $secondBinary],
        ]);

        $response = $this->get('/scrape?'.$query);

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
            $response->getContent()
        );
    }

    public function test_unknown_info_hash_returns_zeroed_stats(): void
    {
        $unknown = strtoupper(bin2hex(random_bytes(20)));
        $binary = hex2bin($unknown);
        $this->assertIsString($binary);

        $response = $this->get('/scrape?info_hash='.urlencode($binary));

        $response->assertOk();

        $expected = [
            'files' => [
                $unknown => [
                    'complete' => 0,
                    'incomplete' => 0,
                    'downloaded' => 0,
                ],
            ],
        ];

        $this->assertSame(
            app(BencodeService::class)->encode($expected),
            $response->getContent()
        );
    }
}
