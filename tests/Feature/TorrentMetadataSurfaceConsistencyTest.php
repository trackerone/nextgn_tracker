<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentMetadataSurfaceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_web_detail_use_the_same_effective_metadata_contract(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt6000006',
            'tmdb_id' => 6006,
            'nfo_text' => 'legacy nfo',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Surface Contract',
            'year' => 2026,
            'type' => 'tv',
            'source' => '',
            'resolution' => null,
            'release_group' => '',
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo' => null,
        ]);

        $expected = [
            'title' => 'Surface Contract',
            'year' => 2026,
            'type' => 'tv',
            'resolution' => null,
            'source' => '',
            'release_group' => '',
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo' => null,
        ];

        $this->actingAs($user)
            ->getJson('/api/torrents/'.$torrent->id)
            ->assertOk()
            ->assertJsonPath('data.metadata', $expected);

        $this->actingAs($user)
            ->get('/torrents/'.$torrent->id)
            ->assertOk()
            ->assertViewHas('metadata', $expected);
    }

    public function test_browse_and_moderation_views_share_the_same_metadata_map_output(): void
    {
        $staff = User::factory()->staff()->create();
        $member = User::factory()->create();

        $approved = Torrent::factory()->create([
            'status' => Torrent::STATUS_APPROVED,
            'type' => 'movie',
            'source' => 'WEB',
        ]);
        $pending = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'type' => 'movie',
            'source' => 'WEB',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $approved->id,
            'type' => 'tv',
            'source' => 'BLURAY',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $pending->id,
            'type' => 'tv',
            'source' => 'BLURAY',
        ]);

        $this->actingAs($member)
            ->get('/torrents')
            ->assertOk()
            ->assertViewHas('torrentMetadata', function (array $metadataMap) use ($approved): bool {
                return ($metadataMap[$approved->id]['type'] ?? null) === 'tv'
                    && ($metadataMap[$approved->id]['source'] ?? null) === 'BLURAY';
            });

        $this->actingAs($staff)
            ->get(route('staff.torrents.moderation.index'))
            ->assertOk()
            ->assertViewHas('torrentMetadata', function (array $metadataMap) use ($pending): bool {
                return ($metadataMap[$pending->id]['type'] ?? null) === 'tv'
                    && ($metadataMap[$pending->id]['source'] ?? null) === 'BLURAY';
            });
    }
}
