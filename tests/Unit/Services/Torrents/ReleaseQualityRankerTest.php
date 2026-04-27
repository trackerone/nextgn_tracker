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
}
