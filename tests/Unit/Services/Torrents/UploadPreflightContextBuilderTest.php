<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Metadata\ExternalMetadataEnrichmentService;
use App\Services\Torrents\UploadPreflightContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UploadPreflightContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private UploadPreflightContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(UploadPreflightContextBuilder::class);
    }

    public function test_builder_can_resolve_from_container(): void
    {
        $resolved = app(UploadPreflightContextBuilder::class);

        $this->assertInstanceOf(UploadPreflightContextBuilder::class, $resolved);
    }

    public function test_for_user_maps_user_state_and_normalized_input(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'is_disabled' => false,
        ]);

        $context = $this->builder->forUser($user, [
            'category' => 'Movies',
            'type' => 'movie',
            'resolution' => '1080p',
            'scene' => false,
            'duplicate' => true,
            'size' => 2_048,
            'ignored_bool_string' => 'true',
        ]);

        $this->assertSame('Movies', $context->category);
        $this->assertSame('movie', $context->type);
        $this->assertSame('1080p', $context->resolution);
        $this->assertSame(false, $context->scene);
        $this->assertSame(true, $context->duplicate);
        $this->assertSame(2_048, $context->size);
        $this->assertTrue($context->isBanned);
        $this->assertFalse($context->isDisabled);
        $this->assertNull($context->metadataComplete);
        $this->assertNull($context->infoHash);
        $this->assertNull($context->existingTorrentId);
        $this->assertTrue($context->extractedMetadata->isEmpty());
    }

    public function test_for_payload_marks_metadata_incomplete_when_size_metadata_is_missing(): void
    {
        $user = User::factory()->create();

        $payload = app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'missing-size',
                'piece length' => 16384,
                'pieces' => str_repeat('a', 20),
            ],
        ]);

        $context = $this->builder->forPayload($user, $payload, [
            'type' => 'movie',
            'resolution' => '2160p',
            'nfo_text' => "Title: Sample Title\nIMDb: tt1234567",
        ]);

        $this->assertSame('movie', $context->type);
        $this->assertSame('2160p', $context->resolution);
        $this->assertSame(false, $context->metadataComplete);
        $this->assertNull($context->size);
        $this->assertNull($context->infoHash);
        $this->assertNull($context->existingTorrentId);
        $this->assertSame('Sample Title', $context->extractedMetadata->title);
        $this->assertSame('tt1234567', $context->extractedMetadata->imdbId);
    }

    public function test_for_payload_resolves_duplicate_and_existing_torrent_id(): void
    {
        $user = User::factory()->create();
        $existingTorrent = Torrent::factory()->create();

        $payload = app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'duplicate-payload',
                'piece length' => 16384,
                'length' => 1024,
                'pieces' => str_repeat('a', 20),
            ],
        ]);

        $info = app(BencodeService::class)->decode($payload)['info'];
        $existingTorrent->forceFill([
            'info_hash' => strtoupper(sha1(app(BencodeService::class)->encode($info))),
        ])->save();

        $context = $this->builder->forPayload($user, $payload);

        $this->assertTrue($context->metadataComplete === true);
        $this->assertSame(1_024, $context->size);
        $this->assertSame(true, $context->duplicate);
        $this->assertNotNull($context->infoHash);
        $this->assertSame($existingTorrent->getKey(), $context->existingTorrentId);
        $this->assertSame('duplicate payload', strtolower((string) $context->extractedMetadata->title));
    }

    public function test_for_payload_applies_external_metadata_enrichment_when_available(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb"]', 'json');
        $user = User::factory()->create();
        $builder = $this->builderWithProviders([
            new FakeUploadExternalMetadataProvider(new ExternalMetadataResult(
                provider: 'tmdb',
                found: true,
                imdbId: 'tt1234567',
                tmdbId: '123',
                title: 'External Title',
                year: 2024,
            )),
        ]);

        $payload = app(BencodeService::class)->encode(['info' => ['name' => 'External.Title.2024.1080p', 'piece length' => 16384, 'length' => 1024, 'pieces' => str_repeat('a', 20)]]);
        $context = $builder->forPayload($user, $payload, ['type' => 'movie', 'resolution' => '1080p']);

        $this->assertContains('imdb_id', $context->metadataEnrichmentAppliedFields);
        $this->assertContains('tmdb_id', $context->metadataEnrichmentAppliedFields);
        $this->assertSame([], $context->metadataEnrichmentConflicts);
    }

    public function test_for_payload_without_external_metadata_keeps_behavior_unchanged(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $user = User::factory()->create();
        $builder = $this->builderWithProviders([new FakeUploadExternalMetadataProvider(ExternalMetadataResult::skipped('tmdb'))]);

        $payload = app(BencodeService::class)->encode(['info' => ['name' => 'No.External.Metadata.2021.1080p', 'piece length' => 16384, 'length' => 1024, 'pieces' => str_repeat('a', 20)]]);
        $context = $builder->forPayload($user, $payload, ['type' => 'movie', 'resolution' => '1080p']);

        $this->assertSame([], $context->metadataEnrichmentAppliedFields);
        $this->assertSame([], $context->metadataEnrichmentConflicts);
    }

    public function test_for_payload_records_conflicts_but_still_succeeds(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $user = User::factory()->create();
        $builder = $this->builderWithProviders([
            new FakeUploadExternalMetadataProvider(new ExternalMetadataResult(provider: 'tmdb', found: true, title: 'Different Title', year: 1999)),
        ]);
        $payload = app(BencodeService::class)->encode(['info' => ['name' => 'Original.Title.2022.1080p', 'piece length' => 16384, 'length' => 1024, 'pieces' => str_repeat('a', 20)]]);
        $context = $builder->forPayload($user, $payload, ['type' => 'movie', 'resolution' => '1080p']);

        $this->assertTrue($context->metadataComplete === true);
        $this->assertNotSame([], $context->metadataEnrichmentConflicts);
    }

    /**
     * @param  list<ExternalMetadataProvider>  $providers
     */
    private function builderWithProviders(array $providers): UploadPreflightContextBuilder
    {
        return new UploadPreflightContextBuilder(
            app(BencodeService::class),
            app(\App\Services\Torrents\TorrentMetadataExtractor::class),
            app(\App\Services\Torrents\UploadReleaseAdvisor::class),
            app(ExternalMetadataConfig::class),
            app(ExternalMetadataEnrichmentService::class),
            $providers,
        );
    }

    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
    }
}

final class FakeUploadExternalMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(private readonly ExternalMetadataResult $result) {}

    public function providerKey(): string
    {
        return 'tmdb';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return true;
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        return $this->result;
    }
}
