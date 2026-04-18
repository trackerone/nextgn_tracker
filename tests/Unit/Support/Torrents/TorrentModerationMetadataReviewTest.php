<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Support\Torrents\TorrentModerationMetadataReview;
use PHPUnit\Framework\TestCase;

final class TorrentModerationMetadataReviewTest extends TestCase
{
    public function test_complete_movie_metadata_is_not_flagged(): void
    {
        $review = TorrentModerationMetadataReview::evaluate([
            'title' => 'Complete Movie',
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => 2024,
        ]);

        $this->assertFalse($review['needs_review']);
        $this->assertSame([], $review['issues']);
        $this->assertSame([], $review['labels']);
    }

    public function test_missing_critical_metadata_is_flagged(): void
    {
        $review = TorrentModerationMetadataReview::evaluate([
            'title' => 'Weak Upload',
            'type' => null,
            'resolution' => '',
            'source' => null,
            'year' => null,
        ]);

        $this->assertTrue($review['needs_review']);
        $this->assertSame(
            ['missing_type', 'missing_resolution', 'missing_source'],
            $review['issues']
        );
    }

    public function test_movie_like_metadata_requires_year(): void
    {
        $review = TorrentModerationMetadataReview::evaluate([
            'title' => 'Movie Without Year',
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'BLURAY',
            'year' => null,
        ]);

        $this->assertTrue($review['needs_review']);
        $this->assertContains('missing_year', $review['issues']);
    }

    public function test_tv_like_metadata_requires_season_episode_signal(): void
    {
        $reviewWithoutEpisode = TorrentModerationMetadataReview::evaluate([
            'title' => 'Show Name',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB',
        ]);

        $this->assertTrue($reviewWithoutEpisode['needs_review']);
        $this->assertContains('missing_season_episode', $reviewWithoutEpisode['issues']);

        $reviewWithEpisode = TorrentModerationMetadataReview::evaluate([
            'title' => 'Show Name S01E02',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB',
        ]);

        $this->assertFalse($reviewWithEpisode['needs_review']);
        $this->assertNotContains('missing_season_episode', $reviewWithEpisode['issues']);
    }
}
