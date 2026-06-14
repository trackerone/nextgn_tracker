<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Recommendations;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Support\Recommendations\RecommendationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_engine_payload_has_internal_foundation_contract(): void
    {
        $this->createMetadata(Torrent::factory()->create(), [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([
            'version',
            'engine',
            'readonly',
            'personalized',
            'uses_user_history',
            'uses_download_history',
            'uses_watch_history',
            'metadata_categories',
            'signal_groups',
            'weights',
            'signals',
        ], array_keys($payload));
        $this->assertSame(1, $payload['version']);
        $this->assertSame('metadata_recommendation_engine_foundation', $payload['engine']);
        $this->assertTrue($payload['readonly']);
        $this->assertFalse($payload['personalized']);
        $this->assertFalse($payload['uses_user_history']);
        $this->assertFalse($payload['uses_download_history']);
        $this->assertFalse($payload['uses_watch_history']);
        $this->assertSame(['sources', 'resolutions', 'languages', 'release_groups'], $payload['metadata_categories']);
        $this->assertSame(['popular', 'trending'], $payload['signal_groups']);
        $this->assertSame(['popular' => 60, 'trending' => 40], $payload['weights']);
    }

    public function test_recommendation_engine_reuses_signal_payload_without_recommendations(): void
    {
        $this->createMetadata(Torrent::factory()->create(), [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['popular']['sources']);
        $this->assertSame([['value' => '1080p', 'count' => 1]], $payload['signals']['popular']['resolutions']);
        $this->assertSame([['value' => 'english', 'count' => 1]], $payload['signals']['popular']['languages']);
        $this->assertSame([['value' => 'NTB', 'count' => 1]], $payload['signals']['popular']['release_groups']);
        $this->assertSame('30d', $payload['signals']['trending']['window']);
        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['trending']['sources']);

        $this->assertArrayNotHasKey('torrents', $payload);
        $this->assertArrayNotHasKey('recommendations', $payload);
        $this->assertArrayNotHasKey('recommended_torrents', $payload);
        $this->assertArrayNotHasKey('scores', $payload);
        $this->assertArrayNotHasKey('users', $payload);
    }

    public function test_recommendation_engine_visibility_filtering_remains_delegated_through_signals(): void
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

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['popular']['sources']);
        $this->assertSame([['value' => 'english', 'count' => 1]], $payload['signals']['popular']['languages']);
        $this->assertSame([['value' => 'WEB-DL', 'count' => 1]], $payload['signals']['trending']['sources']);
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
