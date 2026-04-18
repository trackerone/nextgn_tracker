<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Support;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentMetadataViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_torrent_matches_existing_contract_shape(): void
    {
        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt1000001',
            'tmdb_id' => 1001,
            'nfo_text' => 'legacy nfo',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Persisted Title',
            'year' => 2026,
            'type' => 'tv',
            'source' => 'BLURAY',
            'resolution' => '2160p',
            'release_group' => 'NXT',
            'imdb_id' => 'tt2000002',
            'tmdb_id' => 2002,
            'nfo' => 'persisted nfo',
        ]);

        $torrent->load('metadata');

        $metadata = TorrentMetadataView::forTorrent($torrent);

        $this->assertSame('Persisted Title', $metadata['title']);
        $this->assertSame(2026, $metadata['year']);
        $this->assertSame('tv', $metadata['type']);
        $this->assertSame('BLURAY', $metadata['source']);
        $this->assertSame('2160p', $metadata['resolution']);
        $this->assertSame('NXT', $metadata['release_group']);
        $this->assertSame('tt2000002', $metadata['imdb_id']);
        $this->assertSame(2002, $metadata['tmdb_id']);
        $this->assertSame('persisted nfo', $metadata['nfo']);
    }

    public function test_map_by_torrent_id_builds_keyed_metadata_map(): void
    {
        $first = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
        ]);

        $second = Torrent::factory()->create([
            'type' => 'tv',
            'source' => 'HDTV',
        ]);

        $mapped = TorrentMetadataView::mapByTorrentId([$first, $second]);

        $this->assertSame('movie', $mapped[$first->id]['type']);
        $this->assertSame('WEB', $mapped[$first->id]['source']);
        $this->assertSame('tv', $mapped[$second->id]['type']);
        $this->assertSame('HDTV', $mapped[$second->id]['source']);
    }

    public function test_for_torrent_preserves_null_and_empty_values_from_persisted_metadata(): void
    {
        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt5000005',
            'tmdb_id' => 5005,
            'nfo_text' => 'legacy nfo should not leak',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => null,
            'year' => null,
            'type' => 'movie',
            'source' => null,
            'resolution' => '',
            'release_group' => '',
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo' => null,
        ]);

        $torrent->load('metadata');

        $metadata = TorrentMetadataView::forTorrent($torrent);

        $this->assertNull($metadata['title']);
        $this->assertNull($metadata['year']);
        $this->assertSame('movie', $metadata['type']);
        $this->assertNull($metadata['source']);
        $this->assertSame('', $metadata['resolution']);
        $this->assertSame('', $metadata['release_group']);
        $this->assertNull($metadata['imdb_id']);
        $this->assertNull($metadata['tmdb_id']);
        $this->assertNull($metadata['nfo']);
    }

    public function test_for_torrent_falls_back_when_metadata_relation_is_loaded_as_null(): void
    {
        $torrent = Torrent::factory()->make([
            'id' => 4242,
            'type' => 'tv',
            'source' => 'HDTV',
            'resolution' => '720p',
            'imdb_id' => 'tt4242424',
            'tmdb_id' => 4242,
            'nfo_text' => 'legacy nfo fallback',
        ]);
        $torrent->setRelation('metadata', null);

        $metadata = TorrentMetadataView::forTorrent($torrent);

        $this->assertNull($metadata['title']);
        $this->assertNull($metadata['year']);
        $this->assertSame('tv', $metadata['type']);
        $this->assertSame('HDTV', $metadata['source']);
        $this->assertSame('720p', $metadata['resolution']);
        $this->assertNull($metadata['release_group']);
        $this->assertSame('tt4242424', $metadata['imdb_id']);
        $this->assertSame(4242, $metadata['tmdb_id']);
        $this->assertSame('legacy nfo fallback', $metadata['nfo']);
    }
}
