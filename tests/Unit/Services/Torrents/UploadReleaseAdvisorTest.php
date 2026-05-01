<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\ReleaseQualityRanker;
use App\Services\Torrents\UploadReleaseAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UploadReleaseAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private UploadReleaseAdvisor $advisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advisor = app(UploadReleaseAdvisor::class);
    }

    public function test_same_imdb_id_strengthens_family_match(): void
    {
        $existing = $this->createVisibleTorrentWithMetadata('Different Local Title', 2021, '1080p', 'WEB-DL', imdbId: 'tt1234567');

        $advice = $this->advisor->advise($this->candidate('Other Name', 2024, '1080p', 'WEB-DL', imdbId: 'tt1234567'));

        $this->assertTrue($advice['family_exists']);
        $this->assertSame([$existing->id], $advice['matching_torrent_ids']);
        $this->assertStringContainsString(':imdb:tt1234567', $advice['family_key']);
    }

    public function test_different_technical_fields_are_alternate_version_not_exact_duplicate(): void
    {
        $this->createVisibleTorrentWithMetadata('Family Film', 2024, '2160p', 'BLURAY', tmdbId: 555);

        $advice = $this->advisor->advise($this->candidate('Family Film', 2024, '1080p', 'WEB-DL', tmdbId: 555));

        $this->assertFalse($advice['exact_duplicate_exists']);
        $this->assertTrue($advice['alternate_version_exists']);
        $this->assertTrue($advice['same_external_id_different_version']);
        $this->assertContains('same_external_id_different_version', $advice['warnings']);
    }

    public function test_missing_external_ids_falls_back_to_title_and_year_family(): void
    {
        $existing = $this->createVisibleTorrentWithMetadata('Fallback Film', 2020, '720p', 'HDTV');

        $advice = $this->advisor->advise($this->candidate('Fallback Film', 2020, '1080p', 'WEB-DL'));

        $this->assertSame('movie:fallback film:2020', $advice['family_key']);
        $this->assertSame([$existing->id], $advice['matching_torrent_ids']);
        $this->assertContains('same_title_year_different_version', $advice['warnings']);
    }

    public function test_best_torrent_prefers_enriched_metadata_at_same_resolution_and_source(): void
    {
        $incomplete = $this->createVisibleTorrentWithMetadata('Rank Film', 2024, '1080p', 'WEB-DL');
        $enriched = $this->createVisibleTorrentWithMetadata('Rank Film', 2024, '1080p', 'WEB-DL', imdbId: 'tt7654321', tmdbId: 7654321, releaseGroup: 'NTb');

        $ranker = app(ReleaseQualityRanker::class);
        $incompleteMetadata = TorrentMetadataView::forTorrent($incomplete);
        $enrichedMetadata = TorrentMetadataView::forTorrent($enriched);

        $this->assertNull($incompleteMetadata['imdb_id']);
        $this->assertNull($incompleteMetadata['tmdb_id']);
        $this->assertNull($incompleteMetadata['release_group']);
        $this->assertSame('tt7654321', $enrichedMetadata['imdb_id']);
        $this->assertSame(7654321, $enrichedMetadata['tmdb_id']);
        $this->assertSame('NTb', $enrichedMetadata['release_group']);

        $incompleteScore = $ranker->score($incompleteMetadata);
        $enrichedScore = $ranker->score($enrichedMetadata);

        $this->assertGreaterThan($incompleteScore, $enrichedScore);

        $advice = $this->advisor->advise($this->candidate('Rank Film', 2024, '1080p', 'WEB-DL'));

        $this->assertFalse($advice['better_version_exists']);
        $this->assertFalse($advice['upgrade_available']);
        $this->assertTrue($advice['best_version_is_current_upload']);
        $this->assertNull($advice['upgrade_reason']);
        $this->assertSame($enriched->id, $advice['best_torrent_id']);
        $this->assertSame($enriched->id, $advice['best_version_torrent_id']);
        $this->assertSame([$enriched->id, $incomplete->id], $advice['matching_torrent_ids']);
    }

    public function test_technically_better_existing_version_sets_better_version_exists(): void
    {
        $better = $this->createVisibleTorrentWithMetadata('Tech Film', 2024, '2160p', 'BLURAY');
        $this->createVisibleTorrentWithMetadata('Tech Film', 2024, '1080p', 'WEB-DL', imdbId: 'tt8888888', tmdbId: 8888888, releaseGroup: 'NTb');

        $advice = $this->advisor->advise($this->candidate('Tech Film', 2024, '1080p', 'WEB-DL'));

        $this->assertTrue($advice['better_version_exists']);
        $this->assertTrue($advice['upgrade_available']);
        $this->assertFalse($advice['best_version_is_current_upload']);
        $this->assertSame('A technically better version already exists in this release family.', $advice['upgrade_reason']);
        $this->assertSame($better->id, $advice['best_torrent_id']);
        $this->assertSame($better->id, $advice['best_version_torrent_id']);
        $this->assertContains('better_version_exists', $advice['warnings']);
    }


    public function test_equal_technical_version_with_worse_metadata_does_not_mark_upgrade_available(): void
    {
        $this->createVisibleTorrentWithMetadata('Parity Film', 2024, '1080p', 'WEB-DL', imdbId: 'tt1111111', tmdbId: 1111111, releaseGroup: 'NTb');

        $advice = $this->advisor->advise($this->candidate('Parity Film', 2024, '1080p', 'WEB-DL'));

        $this->assertFalse($advice['better_version_exists']);
        $this->assertFalse($advice['upgrade_available']);
        $this->assertTrue($advice['best_version_is_current_upload']);
        $this->assertNull($advice['upgrade_reason']);
    }

    private function createVisibleTorrentWithMetadata(string $title, int $year, string $resolution, string $source, ?string $imdbId = null, ?int $tmdbId = null, ?string $releaseGroup = null): Torrent
    {
        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'status' => Torrent::STATUS_PUBLISHED,
            'is_approved' => true,
            'is_banned' => false,
            'published_at' => now(),
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => $title,
            'type' => 'movie',
            'year' => $year,
            'resolution' => $resolution,
            'source' => $source,
            'release_group' => $releaseGroup,
            'imdb_id' => $imdbId,
            'tmdb_id' => $tmdbId,
        ]);

        return $torrent;
    }

    private function candidate(string $title, int $year, string $resolution, string $source, ?string $imdbId = null, ?int $tmdbId = null): CanonicalTorrentMetadata
    {
        return CanonicalTorrentMetadata::fromArray([
            'title' => $title,
            'type' => 'movie',
            'year' => $year,
            'resolution' => $resolution,
            'source' => $source,
            'imdb_id' => $imdbId,
            'tmdb_id' => $tmdbId,
        ]);
    }
}
