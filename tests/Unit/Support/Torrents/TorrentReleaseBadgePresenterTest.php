<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Support\Torrents\TorrentReleaseBadgePresenter;
use PHPUnit\Framework\TestCase;

final class TorrentReleaseBadgePresenterTest extends TestCase
{
    public function test_recommended_high_quality_release_gets_expected_badges(): void
    {
        $badges = TorrentReleaseBadgePresenter::browseBadges([
            'completeness' => 'high',
            'missing_fields' => [],
        ], true);

        $this->assertSame(['Recommended', 'High quality'], $badges);
    }

    public function test_medium_quality_mapping_is_explicit(): void
    {
        $badges = TorrentReleaseBadgePresenter::browseBadges([
            'completeness' => 'medium',
            'missing_fields' => ['source'],
        ], false);

        $this->assertSame(['Medium quality', 'Incomplete metadata'], $badges);
    }

    public function test_incomplete_metadata_warning_only_shows_for_critical_missing_fields(): void
    {
        $warningBadges = TorrentReleaseBadgePresenter::browseBadges([
            'completeness' => 'high',
            'missing_fields' => ['type'],
        ], false);

        $safeBadges = TorrentReleaseBadgePresenter::browseBadges([
            'completeness' => 'high',
            'missing_fields' => ['year'],
        ], false);

        $this->assertSame(['High quality', 'Incomplete metadata'], $warningBadges);
        $this->assertSame(['High quality'], $safeBadges);
    }
}
