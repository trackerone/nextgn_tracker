<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Services\Torrents\CanonicalTorrentMetadata;
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

    public function test_no_existing_family_returns_no_warnings(): void
    {
        $advice = $this->advisor->advise($this->candidate('No Match', 2025, '2160p', 'BLURAY'));

        $this->assertSame('movie:no match:2025', $advice['family_key']);
        $this->assertFalse($advice['family_exists']);
        $this->assertFalse($advice['same_quality_exists']);
        $this->assertFalse($advice['better_version_exists']);
        $this->assertNull($advice['best_torrent_id']);
        $this->assertSame([], $advice['matching_torrent_ids']);
        $this->assertSame([], $advice['warnings']);
    }

    public function test_same_family_existing_returns_same_family_warning(): void
    {
        $existing = $this->createVisibleTorrentWithMetadata('Family Film', 2024, '720p', 'HDTV');

        $advice = $this->advisor->advise($this->candidate('Family Film', 2024, '2160p', 'BLURAY'));

        $this->assertTrue($advice['family_exists']);
        $this->assertSame([$existing->id], $advice['matching_torrent_ids']);
        $this->assertContains('same_family_exists', $advice['warnings']);
    }

    public function test_same_quality_existing_returns_same_quality_warning(): void
    {
        $existing = $this->createVisibleTorrentWithMetadata('Quality Film', 2023, '1080p', 'WEB-DL');

        $advice = $this->advisor->advise($this->candidate('Quality Film', 2023, '1080p', 'WEB-DL'));

        $this->assertTrue($advice['same_quality_exists']);
        $this->assertContains('same_quality_exists', $advice['warnings']);
        $this->assertSame($existing->id, $advice['best_torrent_id']);
    }

    public function test_better_version_existing_returns_better_version_warning(): void
    {
        $best = $this->createVisibleTorrentWithMetadata('Upgrade Film', 2024, '2160p', 'BLURAY');
        $this->createVisibleTorrentWithMetadata('Upgrade Film', 2024, '1080p', 'WEB-DL');

        $advice = $this->advisor->advise($this->candidate('Upgrade Film', 2024, '720p', 'HDTV'));

        $this->assertTrue($advice['better_version_exists']);
        $this->assertContains('better_version_exists', $advice['warnings']);
        $this->assertSame($best->id, $advice['best_torrent_id']);
    }

    public function test_candidate_better_than_existing_does_not_set_better_version_warning(): void
    {
        $this->createVisibleTorrentWithMetadata('No Better Warning', 2022, '720p', 'HDTV');

        $advice = $this->advisor->advise($this->candidate('No Better Warning', 2022, '2160p', 'BLURAY'));

        $this->assertFalse($advice['better_version_exists']);
        $this->assertNotContains('better_version_exists', $advice['warnings']);
    }

    private function createVisibleTorrentWithMetadata(string $title, int $year, string $resolution, string $source): Torrent
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
        ]);

        return $torrent;
    }

    private function candidate(string $title, int $year, string $resolution, string $source): CanonicalTorrentMetadata
    {
        return CanonicalTorrentMetadata::fromArray([
            'title' => $title,
            'type' => 'movie',
            'year' => $year,
            'resolution' => $resolution,
            'source' => $source,
        ]);
    }
}
