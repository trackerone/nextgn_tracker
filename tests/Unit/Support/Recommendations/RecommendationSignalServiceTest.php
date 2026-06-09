<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Recommendations;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Support\Recommendations\RecommendationSignalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationSignalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_signal_payload_has_foundation_shape(): void
    {
        $this->createMetadata(Torrent::factory()->create(), [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $payload = app(RecommendationSignalService::class)->payload();

        $this->assertSame([
            'version',
            'engine',
            'personalized',
            'uses_user_history',
            'uses_download_history',
            'signals',
        ], array_keys($payload));
        $this->assertSame(1, $payload['version']);
        $this->assertSame('metadata_signals_foundation', $payload['engine']);
        $this->assertFalse($payload['personalized']);
        $this->assertFalse($payload['uses_user_history']);
        $this->assertFalse($payload['uses_download_history']);

        $this->assertSame(['popular', 'trending'], array_keys($payload['signals']));
        $this->assertSame([
            'sources',
            'resolutions',
            'languages',
            'release_groups',
        ], array_keys($payload['signals']['popular']));
        $this->assertSame([
            'window',
            'sources',
            'resolutions',
            'release_groups',
        ], array_keys($payload['signals']['trending']));
        $this->assertSame('30d', $payload['signals']['trending']['window']);

        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['popular']['sources']);
        $this->assertSame([['value' => '1080p', 'count' => 1]], $payload['signals']['popular']['resolutions']);
        $this->assertSame([['value' => 'english', 'count' => 1]], $payload['signals']['popular']['languages']);
        $this->assertSame([['value' => 'NTB', 'count' => 1]], $payload['signals']['popular']['release_groups']);
        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['trending']['sources']);
    }

    public function test_recommendation_signals_only_include_visible_torrents(): void
    {
        $this->createMetadata(Torrent::factory()->create(), [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata(Torrent::factory()->banned()->create(), [
            'source' => 'CAM',
            'resolution' => '480p',
            'language' => 'italian',
            'release_group' => 'Hidden',
        ]);
        $this->createMetadata(Torrent::factory()->unapproved()->create(), [
            'source' => 'DVDRip',
            'resolution' => '576p',
            'language' => 'portuguese',
            'release_group' => 'Pending',
        ]);
        $this->createMetadata(Torrent::factory()->create(['uploaded_at' => now()->subDays(31)]), [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'language' => 'french',
            'release_group' => 'Archive',
        ]);

        $payload = app(RecommendationSignalService::class)->payload();

        $this->assertSame([
            ['value' => 'BluRay', 'count' => 1],
            ['value' => 'WEB-DL', 'count' => 1],
        ], $payload['signals']['popular']['sources']);
        $this->assertSame([
            ['value' => 'english', 'count' => 1],
            ['value' => 'french', 'count' => 1],
        ], $payload['signals']['popular']['languages']);
        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['trending']['sources']);
        $this->assertSame([['value' => '1080p', 'count' => 1]], $payload['signals']['trending']['resolutions']);
        $this->assertSame([['value' => 'NTB', 'count' => 1]], $payload['signals']['trending']['release_groups']);
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    private function createMetadata(Torrent $torrent, array $attributes): void
    {
        TorrentMetadata::query()->create(array_merge([
            'torrent_id' => $torrent->id,
        ], $attributes));
    }
}
