<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Services\Torrents\ReleaseQualityRanker;
use Tests\TestCase;

final class ReleaseQualityRankerTest extends TestCase
{
    public function test_scores_2160p_above_1080p(): void
    {
        $ranker = app(ReleaseQualityRanker::class);

        $score2160 = $ranker->score([
            'title' => 'Example',
            'year' => 2024,
            'resolution' => '2160p',
            'source' => 'WEB-DL',
            'imdb_id' => 'tt1234567',
            'tmdb_id' => 123,
        ]);

        $score1080 = $ranker->score([
            'title' => 'Example',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'imdb_id' => 'tt1234567',
            'tmdb_id' => 123,
        ]);

        $this->assertGreaterThan($score1080, $score2160);
    }

    public function test_scores_bluray_above_web_dl_at_same_resolution(): void
    {
        $ranker = app(ReleaseQualityRanker::class);

        $bluray = $ranker->score([
            'title' => 'Example',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'BLURAY',
        ]);

        $webDl = $ranker->score([
            'title' => 'Example',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $this->assertGreaterThan($webDl, $bluray);
    }

    public function test_enriched_metadata_beats_incomplete_metadata_at_same_resolution_and_source(): void
    {
        $ranker = app(ReleaseQualityRanker::class);

        $enriched = $ranker->score([
            'title' => 'Example',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'release_group' => 'NTb',
            'imdb_id' => 'tt1234567',
            'tmdb_id' => 123,
        ]);

        $incomplete = $ranker->score([
            'title' => null,
            'year' => null,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'release_group' => null,
            'imdb_id' => null,
            'tmdb_id' => null,
        ]);

        $this->assertGreaterThan($incomplete, $enriched);
    }

    public function test_fallback_behavior_unchanged_when_no_metadata_exists(): void
    {
        $ranker = app(ReleaseQualityRanker::class);

        $score = $ranker->score([]);

        $this->assertSame(197, $score);
    }
}
