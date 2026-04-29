<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata;

use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataEnrichmentService;
use App\Services\Torrents\CanonicalTorrentMetadata;
use Tests\TestCase;

final class ExternalMetadataEnrichmentServiceTest extends TestCase
{
    public function test_it_fills_missing_descriptive_fields(): void
    {
        $service = new ExternalMetadataEnrichmentService;
        $canonical = CanonicalTorrentMetadata::fromArray([
            'title' => null,
            'year' => null,
            'raw_payload' => [],
        ]);

        $result = $service->enrich($canonical, new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            title: 'External Title',
            year: 2024,
            overview: 'External overview',
            posterUrl: 'https://img.test/poster.jpg',
            backdropUrl: 'https://img.test/backdrop.jpg',
            rawPayload: [
                'genres' => ['Drama'],
                'runtime' => 118,
            ],
        ));

        $this->assertSame('External Title', $result->metadata->title);
        $this->assertSame(2024, $result->metadata->year);
        $this->assertSame('External overview', $result->metadata->rawPayload['overview']);
        $this->assertContains('title', $result->appliedFields);
        $this->assertContains('year', $result->appliedFields);
    }

    public function test_it_does_not_overwrite_local_technical_fields(): void
    {
        $service = new ExternalMetadataEnrichmentService;
        $canonical = CanonicalTorrentMetadata::fromArray([
            'resolution' => '2160p',
            'source' => 'BLURAY',
            'release_group' => 'GROUP',
            'raw_name' => 'Local.Raw.Name',
            'parsed_name' => 'Local Parsed Name',
        ]);

        $result = $service->enrich($canonical, new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            rawPayload: [
                'resolution' => '720p',
                'source' => 'WEB-DL',
                'release_group' => 'OTHER',
                'raw_name' => 'External.Raw.Name',
                'parsed_name' => 'External Parsed Name',
            ],
        ));

        $this->assertSame('2160p', $result->metadata->resolution);
        $this->assertSame('BLURAY', $result->metadata->source);
        $this->assertSame('GROUP', $result->metadata->releaseGroup);
        $this->assertSame('Local.Raw.Name', $result->metadata->rawName);
        $this->assertSame('Local Parsed Name', $result->metadata->parsedName);
    }

    public function test_it_records_conflict_when_external_title_or_year_differs(): void
    {
        $service = new ExternalMetadataEnrichmentService;
        $canonical = CanonicalTorrentMetadata::fromArray([
            'title' => 'Local Title',
            'year' => 2022,
        ]);

        $result = $service->enrich($canonical, new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            title: 'External Title',
            year: 2020,
        ));

        $this->assertSame('Local Title', $result->metadata->title);
        $this->assertSame(2022, $result->metadata->year);
        $this->assertNotEmpty($result->conflicts);
        $this->assertStringContainsString('title conflict', $result->conflicts[0]);
    }

    public function test_it_preserves_existing_imdb_and_tmdb_ids_unless_missing(): void
    {
        $service = new ExternalMetadataEnrichmentService;
        $withIds = CanonicalTorrentMetadata::fromArray([
            'imdb_id' => 'tt1234567',
            'tmdb_id' => 111,
        ]);

        $kept = $service->enrich($withIds, new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            imdbId: 'tt7654321',
            tmdbId: '222',
        ));

        $this->assertSame('tt1234567', $kept->metadata->imdbId);
        $this->assertSame(111, $kept->metadata->tmdbId);

        $withoutIds = CanonicalTorrentMetadata::fromArray([]);
        $filled = $service->enrich($withoutIds, new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            imdbId: 'tt7654321',
            tmdbId: '222',
        ));

        $this->assertSame('tt7654321', $filled->metadata->imdbId);
        $this->assertSame(222, $filled->metadata->tmdbId);
    }

    public function test_it_handles_null_or_empty_external_metadata_safely(): void
    {
        $service = new ExternalMetadataEnrichmentService;
        $canonical = CanonicalTorrentMetadata::fromArray([
            'title' => 'Local Title',
            'year' => 2022,
        ]);

        $nullResult = $service->enrich($canonical, null);
        $emptyResult = $service->enrich($canonical, ExternalMetadataResult::skipped('tmdb'));

        $this->assertSame('Local Title', $nullResult->metadata->title);
        $this->assertSame([], $nullResult->appliedFields);
        $this->assertSame('Local Title', $emptyResult->metadata->title);
        $this->assertSame([], $emptyResult->appliedFields);
    }
}
