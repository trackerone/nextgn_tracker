<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentMetadataReadWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_torrent_detail_prefers_persisted_metadata_row(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'resolution' => '720p',
            'imdb_id' => 'tt1111111',
            'tmdb_id' => 111,
            'nfo_text' => 'legacy nfo',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'type' => 'tv',
            'resolution' => '2160p',
            'source' => 'web-dl',
            'release_group' => 'ntb',
            'year' => 2024,
            'imdb_id' => 'tt2222222',
            'tmdb_id' => 222,
            'nfo' => 'persisted nfo',
        ]);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $response->assertSee('Tv');
        $response->assertSee('2160p');
        $response->assertSee('WEB-DL');
        $response->assertSee('NTB');
        $response->assertSee('2024');
        $response->assertSee('tt2222222');
        $response->assertSee('222');
        $response->assertSee('persisted nfo');
        $response->assertDontSee('legacy nfo');
    }

    public function test_torrent_detail_hides_empty_metadata_fields(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'resolution' => null,
            'source' => null,
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'type' => null,
            'resolution' => null,
            'source' => '',
            'release_group' => '',
            'year' => null,
        ]);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $response->assertDontSee('Resolution');
        $response->assertDontSee('Release group');
        $response->assertDontSee('Unknown');
    }

    public function test_torrent_browse_renders_type_from_metadata_view_without_lazy_loading(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'name' => 'Web Browse Metadata',
            'type' => 'movie',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'bluray',
            'release_group' => 'flux',
            'year' => 2025,
        ]);

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($user)->get(route('torrents.index'));
        } finally {
            Model::preventLazyLoading(false);
        }

        $response->assertOk();
        $response->assertSeeInOrder(['Web Browse Metadata', 'Tv']);
        $response->assertSee('1080p');
        $response->assertSee('BLURAY');
        $response->assertSee('FLUX');
        $response->assertSee('2025');
    }

    public function test_torrent_detail_falls_back_to_legacy_columns_when_metadata_row_is_missing(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'resolution' => '1080p',
            'imdb_id' => 'tt5555555',
            'tmdb_id' => 555,
            'nfo_text' => 'legacy-only nfo',
        ]);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $response->assertSee('Movie');
        $response->assertSee('1080p');
        $response->assertSee('tt5555555');
        $response->assertSee('555');
        $response->assertSee('legacy-only nfo');
    }
}
