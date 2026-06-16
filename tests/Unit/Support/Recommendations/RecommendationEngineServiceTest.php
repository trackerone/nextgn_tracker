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
            'candidate_groups',
            'recommendation_groups',
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
        $this->assertSame([['source' => 'WEB-DL', 'resolution' => '1080p']], $payload['candidate_groups']);
        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '1080p', 'language' => 'english'],
        ], $payload['recommendation_groups']);
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
        $this->assertSame([['source' => 'WEB-DL', 'resolution' => '1080p']], $payload['candidate_groups']);
        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '1080p', 'language' => 'english'],
        ], $payload['recommendation_groups']);

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
        $this->assertSame([['source' => 'WEB-DL', 'resolution' => '1080p']], $payload['candidate_groups']);
        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '1080p', 'language' => 'english'],
        ], $payload['recommendation_groups']);
    }

    public function test_recommendation_engine_candidate_groups_are_metadata_only_combinations(): void
    {
        $this->createMetadata(Torrent::factory()->create([
            'uploaded_at' => now()->subDay(),
        ]), [
            'source' => 'WEB-DL',
            'resolution' => '2160p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata(Torrent::factory()->create([
            'uploaded_at' => now()->subDays(45),
        ]), [
            'source' => 'BluRay',
            'resolution' => '1080p',
            'language' => 'french',
            'release_group' => 'CtrlHD',
        ]);

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '2160p'],
            ['source' => 'WEB-DL', 'resolution' => '1080p'],
            ['source' => 'BluRay', 'resolution' => '2160p'],
            ['source' => 'BluRay', 'resolution' => '1080p'],
        ], $payload['candidate_groups']);

        foreach ($payload['candidate_groups'] as $candidateGroup) {
            $this->assertSame(['source', 'resolution'], array_keys($candidateGroup));
            $this->assertArrayNotHasKey('torrent_id', $candidateGroup);
            $this->assertArrayNotHasKey('score', $candidateGroup);
            $this->assertArrayNotHasKey('rank', $candidateGroup);
        }
    }

    public function test_recommendation_engine_recommendation_groups_are_readonly_metadata_output_combinations(): void
    {
        $this->createMetadata(Torrent::factory()->create([
            'uploaded_at' => now()->subDay(),
        ]), [
            'source' => 'WEB-DL',
            'resolution' => '2160p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata(Torrent::factory()->create([
            'uploaded_at' => now()->subDays(45),
        ]), [
            'source' => 'BluRay',
            'resolution' => '1080p',
            'language' => 'french',
            'release_group' => 'CtrlHD',
        ]);

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '2160p', 'language' => 'english'],
            ['source' => 'WEB-DL', 'resolution' => '2160p', 'language' => 'french'],
            ['source' => 'WEB-DL', 'resolution' => '1080p', 'language' => 'english'],
            ['source' => 'WEB-DL', 'resolution' => '1080p', 'language' => 'french'],
            ['source' => 'BluRay', 'resolution' => '2160p', 'language' => 'english'],
            ['source' => 'BluRay', 'resolution' => '2160p', 'language' => 'french'],
            ['source' => 'BluRay', 'resolution' => '1080p', 'language' => 'english'],
            ['source' => 'BluRay', 'resolution' => '1080p', 'language' => 'french'],
        ], $payload['recommendation_groups']);

        foreach ($payload['recommendation_groups'] as $recommendationGroup) {
            $this->assertSame(['source', 'resolution', 'language'], array_keys($recommendationGroup));
            $this->assertArrayNotHasKey('torrent_id', $recommendationGroup);
            $this->assertArrayNotHasKey('score', $recommendationGroup);
            $this->assertArrayNotHasKey('rank', $recommendationGroup);
            $this->assertArrayNotHasKey('user_id', $recommendationGroup);
        }
    }

    public function test_recommendation_engine_recommendation_groups_respect_visibility_filtered_signals(): void
    {
        $this->createMetadata(Torrent::factory()->create(), [
            'source' => 'WEB-DL',
            'resolution' => '2160p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata(Torrent::factory()->banned()->create(), [
            'source' => 'CAM',
            'resolution' => '480p',
            'language' => 'italian',
            'release_group' => 'Hidden',
        ]);

        $payload = app(RecommendationEngineService::class)->payload();

        $this->assertSame([
            ['source' => 'WEB-DL', 'resolution' => '2160p', 'language' => 'english'],
        ], $payload['recommendation_groups']);
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
