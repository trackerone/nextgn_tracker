<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentMetadataReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_torrent_detail_prefers_persisted_metadata_row(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '720p',
            'imdb_id' => 'tt1111111',
            'tmdb_id' => 111,
            'nfo_text' => 'legacy nfo',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Persisted Title',
            'year' => 2026,
            'type' => 'tv',
            'source' => 'WEB-DL',
            'resolution' => '2160p',
            'release_group' => 'NXT',
            'imdb_id' => 'tt2222222',
            'tmdb_id' => 222,
            'nfo' => 'persisted nfo',
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.metadata.title', 'Persisted Title');
        $response->assertJsonPath('data.metadata.year', 2026);
        $response->assertJsonPath('data.metadata.type', 'tv');
        $response->assertJsonPath('data.metadata.source', 'WEB-DL');
        $response->assertJsonPath('data.metadata.resolution', '2160p');
        $response->assertJsonPath('data.metadata.release_group', 'NXT');
        $response->assertJsonPath('data.metadata.imdb_id', 'tt2222222');
        $response->assertJsonPath('data.metadata.tmdb_id', 222);
        $response->assertJsonPath('data.metadata.nfo', 'persisted nfo');
    }

    public function test_torrent_browse_returns_metadata_fields_from_persisted_metadata(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt3333333',
            'tmdb_id' => 333,
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'source' => 'BLURAY',
            'resolution' => '2160p',
            'release_group' => 'GRP',
            'imdb_id' => 'tt4444444',
            'tmdb_id' => 444,
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $torrent->id);
        $response->assertJsonPath('data.0.metadata.source', 'BLURAY');
        $response->assertJsonPath('data.0.metadata.resolution', '2160p');
        $response->assertJsonPath('data.0.metadata.release_group', 'GRP');
        $response->assertJsonPath('data.0.metadata.imdb_id', 'tt4444444');
        $response->assertJsonPath('data.0.metadata.tmdb_id', 444);
    }

    public function test_torrent_metadata_falls_back_to_legacy_columns_when_row_is_missing(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt5555555',
            'tmdb_id' => 555,
            'nfo_text' => 'legacy-only nfo',
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.metadata.title', null);
        $response->assertJsonPath('data.metadata.type', 'movie');
        $response->assertJsonPath('data.metadata.source', 'WEB');
        $response->assertJsonPath('data.metadata.resolution', '1080p');
        $response->assertJsonPath('data.metadata.release_group', null);
        $response->assertJsonPath('data.metadata.imdb_id', 'tt5555555');
        $response->assertJsonPath('data.metadata.tmdb_id', 555);
        $response->assertJsonPath('data.metadata.nfo', 'legacy-only nfo');
    }
}
