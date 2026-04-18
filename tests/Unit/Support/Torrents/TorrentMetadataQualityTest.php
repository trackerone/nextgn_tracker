<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Support\Torrents\TorrentMetadataQuality;
use PHPUnit\Framework\TestCase;

final class TorrentMetadataQualityTest extends TestCase
{
    public function test_complete_movie_metadata_gets_high_completeness_score(): void
    {
        $quality = TorrentMetadataQuality::evaluate([
            'title' => 'Complete Movie',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'WEB-DL',
            'year' => 2025,
        ]);

        $this->assertSame(100, $quality['score']);
        $this->assertSame('high', $quality['completeness']);
        $this->assertSame('ok', $quality['review_category']);
        $this->assertSame([], $quality['missing_fields']);
        $this->assertSame([], $quality['issues']);
        $this->assertSame([], $quality['labels']);
    }

    public function test_missing_critical_metadata_lowers_completeness_and_marks_critical_review(): void
    {
        $quality = TorrentMetadataQuality::evaluate([
            'title' => 'Weak Upload',
            'type' => null,
            'resolution' => null,
            'source' => '',
        ]);

        $this->assertSame(20, $quality['score']);
        $this->assertSame('low', $quality['completeness']);
        $this->assertSame('critical', $quality['review_category']);
        $this->assertSame(['type', 'resolution', 'source'], $quality['missing_fields']);
        $this->assertSame(['missing_type', 'missing_resolution', 'missing_source'], $quality['issues']);
    }

    public function test_movie_like_quality_requires_year_and_returns_explicit_structured_output(): void
    {
        $quality = TorrentMetadataQuality::evaluate([
            'title' => 'Movie Without Year',
            'type' => 'documentary',
            'resolution' => '1080p',
            'source' => 'BLURAY',
            'year' => null,
        ]);

        $this->assertSame(90, $quality['score']);
        $this->assertSame('high', $quality['completeness']);
        $this->assertSame('warning', $quality['review_category']);
        $this->assertSame(['year'], $quality['missing_fields']);
        $this->assertSame(['missing_year'], $quality['issues']);
        $this->assertSame(['Missing year'], $quality['labels']);
    }

    public function test_tv_like_quality_requires_season_episode_signal(): void
    {
        $withoutEpisode = TorrentMetadataQuality::evaluate([
            'title' => 'Show Name',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB',
        ]);

        $this->assertSame(90, $withoutEpisode['score']);
        $this->assertSame('warning', $withoutEpisode['review_category']);
        $this->assertSame(['season_episode'], $withoutEpisode['missing_fields']);
        $this->assertSame(['missing_season_episode'], $withoutEpisode['issues']);

        $withEpisode = TorrentMetadataQuality::evaluate([
            'title' => 'Show Name S01E02',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB',
        ]);

        $this->assertSame(100, $withEpisode['score']);
        $this->assertSame('ok', $withEpisode['review_category']);
        $this->assertSame([], $withEpisode['issues']);
    }
}
