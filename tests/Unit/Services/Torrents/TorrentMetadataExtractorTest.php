<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Services\BencodeService;
use App\Services\Torrents\TorrentMetadataExtractor;
use Tests\TestCase;

final class TorrentMetadataExtractorTest extends TestCase
{
    private TorrentMetadataExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = app(TorrentMetadataExtractor::class);
    }

    public function test_extracts_metadata_from_nfo_with_imdb_and_tmdb_hints(): void
    {
        $metadata = $this->extractor->extract(
            torrentPayload: $this->samplePayload('Movie.Title.2025.1080p.WEB-DL-GRP'),
            rawNfo: "Title: Movie Title\nIMDb: https://www.imdb.com/title/tt1234567/\nTMDB: https://www.themoviedb.org/movie/998877"
        );

        $this->assertSame('Movie Title', $metadata->title);
        $this->assertSame('1080p', $metadata->resolution);
        $this->assertSame('WEB-DL', $metadata->source);
        $this->assertSame('GRP', $metadata->releaseGroup);
        $this->assertSame('tt1234567', $metadata->imdbId);
        $this->assertSame('https://www.imdb.com/title/tt1234567/', $metadata->imdbUrl);
        $this->assertSame('998877', $metadata->tmdbId);
        $this->assertSame('https://www.themoviedb.org/movie/998877', $metadata->tmdbUrl);
        $this->assertNotNull($metadata->rawNfo);
    }

    public function test_extracts_non_identity_metadata_when_imdb_tmdb_are_absent(): void
    {
        $metadata = $this->extractor->extract(
            torrentPayload: $this->samplePayload('Show.Name.S01E01.2160p.BluRay-TEAM'),
            rawNfo: 'Release Notes only'
        );

        $this->assertSame('Show Name S01E01', $metadata->title);
        $this->assertSame('2160p', $metadata->resolution);
        $this->assertSame('BLURAY', $metadata->source);
        $this->assertSame('TEAM', $metadata->releaseGroup);
        $this->assertNull($metadata->imdbId);
        $this->assertNull($metadata->tmdbId);
    }

    public function test_returns_empty_identity_hints_when_nfo_is_absent(): void
    {
        $metadata = $this->extractor->extract(
            torrentPayload: $this->samplePayload('Docu.Name.720p.HDTV-ABC'),
            rawNfo: null,
        );

        $this->assertSame('Docu Name', $metadata->title);
        $this->assertSame('720p', $metadata->resolution);
        $this->assertSame('HDTV', $metadata->source);
        $this->assertSame('ABC', $metadata->releaseGroup);
        $this->assertNull($metadata->imdbId);
        $this->assertNull($metadata->tmdbId);
        $this->assertNull($metadata->rawNfo);
    }

    private function samplePayload(string $name): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => $name,
                'piece length' => 16384,
                'length' => 2048,
                'pieces' => str_repeat('a', 20),
            ],
        ]);
    }
}
