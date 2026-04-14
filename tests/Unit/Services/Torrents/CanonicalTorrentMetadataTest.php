<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\TorrentExtractedMetadata;
use PHPUnit\Framework\TestCase;

final class CanonicalTorrentMetadataTest extends TestCase
{
    public function test_it_normalizes_empty_strings_and_generates_urls(): void
    {
        $metadata = CanonicalTorrentMetadata::fromArray([
            'title' => '  ',
            'type' => ' movie ',
            'resolution' => ' 1080p ',
            'source' => ' WEB-DL ',
            'release_group' => ' [GRP] ',
            'imdb_id' => 'https://www.imdb.com/title/tt1234567/',
            'tmdb_id' => 'movie/9988',
            'nfo' => '',
            'raw_name' => ' Name ',
            'parsed_name' => ' Parsed ',
            'year' => '2025',
        ]);

        $this->assertNull($metadata->title);
        $this->assertSame('movie', $metadata->type);
        $this->assertSame('1080p', $metadata->resolution);
        $this->assertSame('WEB-DL', $metadata->source);
        $this->assertSame('GRP', $metadata->releaseGroup);
        $this->assertSame('tt1234567', $metadata->imdbId);
        $this->assertSame('https://www.imdb.com/title/tt1234567/', $metadata->imdbUrl);
        $this->assertSame(9988, $metadata->tmdbId);
        $this->assertSame('https://www.themoviedb.org/movie/9988', $metadata->tmdbUrl);
        $this->assertNull($metadata->nfo);
        $this->assertSame('Name', $metadata->rawName);
        $this->assertSame('Parsed', $metadata->parsedName);
        $this->assertSame(2025, $metadata->year);
    }

    public function test_it_rejects_invalid_external_ids(): void
    {
        $metadata = CanonicalTorrentMetadata::fromArray([
            'imdb_id' => 'abc123',
            'tmdb_id' => 'nope',
            'year' => '1875',
        ]);

        $this->assertNull($metadata->imdbId);
        $this->assertNull($metadata->imdbUrl);
        $this->assertNull($metadata->tmdbId);
        $this->assertNull($metadata->tmdbUrl);
        $this->assertNull($metadata->year);
    }

    public function test_it_falls_back_to_explicit_resolution_and_source_when_extracted_values_are_missing(): void
    {
        $extracted = new TorrentExtractedMetadata(
            title: 'Movie Title',
            year: '2025',
            resolution: null,
            source: null,
            releaseGroup: null,
            imdbId: 'tt1234567',
            imdbUrl: null,
            tmdbId: '9988',
            tmdbUrl: null,
            rawNfo: 'NFO',
            rawName: 'Movie.Title',
            parsedName: 'Movie Title',
        );

        $metadata = CanonicalTorrentMetadata::fromExtractedMetadata(
            $extracted,
            type: 'movie',
            resolution: '1080p',
            source: 'WEB-DL',
        );

        $this->assertSame('movie', $metadata->type);
        $this->assertSame('1080p', $metadata->resolution);
        $this->assertSame('WEB-DL', $metadata->source);
    }

    public function test_it_prefers_extracted_source_over_fallback_source(): void
    {
        $extracted = new TorrentExtractedMetadata(
            title: 'Movie Title',
            year: '2025',
            resolution: '1080p',
            source: 'WEB-DL',
            releaseGroup: null,
            imdbId: null,
            imdbUrl: null,
            tmdbId: null,
            tmdbUrl: null,
            rawNfo: 'NFO',
            rawName: 'Movie.Title',
            parsedName: 'Movie Title',
        );

        $metadata = CanonicalTorrentMetadata::fromExtractedMetadata(
            $extracted,
            type: 'movie',
            resolution: '2160p',
            source: 'bluray',
        );

        $this->assertSame('1080p', $metadata->resolution);
        $this->assertSame('WEB-DL', $metadata->source);
    }
}
