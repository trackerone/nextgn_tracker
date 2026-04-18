<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentFollow;
use App\Models\TorrentMetadata;
use App\Services\Torrents\TorrentFollowMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentFollowMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_torrent_using_metadata_title_and_preferences(): void
    {
        $follow = TorrentFollow::factory()->create([
            'title' => 'The Last Planet',
            'normalized_title' => 'the last planet',
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => 2024,
        ]);

        $torrent = Torrent::factory()->create([
            'name' => 'Unrelated Legacy Name',
            'type' => 'music',
            'resolution' => '720p',
            'source' => 'HDTV',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'The Last Planet',
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => 2024,
        ]);

        $this->assertTrue(app(TorrentFollowMatcher::class)->matchesFollow($follow, $torrent));
    }

    public function test_it_supports_partial_preferences(): void
    {
        $follow = TorrentFollow::factory()->create([
            'title' => 'City Lights',
            'normalized_title' => 'city lights',
            'type' => null,
            'source' => null,
            'year' => null,
            'resolution' => '2160p',
        ]);

        $torrent = Torrent::factory()->create([
            'name' => 'City Lights release',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'City Lights',
            'resolution' => '2160p',
            'type' => 'tv',
            'source' => 'WEB-DL',
            'year' => 2025,
        ]);

        $this->assertTrue(app(TorrentFollowMatcher::class)->matchesFollow($follow, $torrent));
    }

    public function test_it_returns_no_match_when_criteria_do_not_fit(): void
    {
        $follow = TorrentFollow::factory()->create([
            'title' => 'Night Shift',
            'normalized_title' => 'night shift',
            'resolution' => '2160p',
        ]);

        $torrent = Torrent::factory()->create();
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Night Shift',
            'resolution' => '1080p',
        ]);

        $this->assertFalse(app(TorrentFollowMatcher::class)->matchesFollow($follow, $torrent));
    }

    public function test_it_matches_title_only_follow_when_torrent_has_no_metadata(): void
    {
        $follow = TorrentFollow::factory()->create([
            'title' => 'Legacy One',
            'normalized_title' => 'legacy one',
            'type' => null,
            'resolution' => null,
            'source' => null,
            'year' => null,
        ]);

        $torrent = Torrent::factory()->create([
            'name' => 'Legacy One 2026 Proper',
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $this->assertTrue(app(TorrentFollowMatcher::class)->matchesFollow($follow, $torrent));
    }

    public function test_it_does_not_match_metadata_filters_when_torrent_has_no_metadata(): void
    {
        $follow = TorrentFollow::factory()->create([
            'title' => 'Legacy One',
            'normalized_title' => 'legacy one',
            'resolution' => '1080p',
        ]);

        $torrent = Torrent::factory()->create([
            'name' => 'Legacy One 2026 Proper',
            'resolution' => '1080p',
        ]);

        $this->assertFalse(app(TorrentFollowMatcher::class)->matchesFollow($follow, $torrent));
    }
}
