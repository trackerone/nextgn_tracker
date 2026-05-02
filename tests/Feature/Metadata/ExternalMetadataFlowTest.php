<?php

declare(strict_types=1);

namespace Tests\Feature\Metadata;

use App\Actions\Torrents\PublishTorrentAction;
use App\Enums\TorrentStatus;
use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Metadata\ExternalMetadataEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ExternalMetadataFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_dispatches_enrichment_job_when_auto_on_publish_enabled(): void
    {
        Queue::fake();

        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.enrichment.auto_on_publish', 'true', 'bool');

        $moderator = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create(['status' => TorrentStatus::Pending]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        Queue::assertPushed(EnrichTorrentExternalMetadata::class);
    }

    public function test_publish_does_not_dispatch_enrichment_job_when_auto_on_publish_disabled(): void
    {
        Queue::fake();

        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.enrichment.auto_on_publish', 'false', 'bool');

        $moderator = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create(['status' => TorrentStatus::Pending]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        Queue::assertNotPushed(EnrichTorrentExternalMetadata::class);
    }

    public function test_enrichment_job_updates_torrent_external_metadata(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');

        $torrent = Torrent::factory()->create();

        $enricher = new ExternalMetadataEnricher(
            app(ExternalMetadataConfig::class),
            [
                new FakeFlowExternalMetadataProvider(
                    key: 'tmdb',
                    result: new ExternalMetadataResult(
                        provider: 'tmdb',
                        found: true,
                        imdbId: 'tt1234567',
                        tmdbId: '123',
                        title: 'Flow Result',
                        year: 2020,
                        mediaType: 'movie',
                    )
                ),
                new FakeFlowExternalMetadataProvider('trakt', ExternalMetadataResult::skipped('trakt'), supports: false),
                new FakeFlowExternalMetadataProvider('imdb', ExternalMetadataResult::skipped('imdb'), supports: false),
            ]
        );

        $job = new EnrichTorrentExternalMetadata($torrent->id);
        $job->handle($enricher);

        $this->assertDatabaseHas('torrent_external_metadata', [
            'torrent_id' => $torrent->id,
            'enrichment_status' => 'enriched',
        ]);
    }

    public function test_torrent_api_resources_include_external_metadata_when_available(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        TorrentExternalMetadata::factory()->for($torrent)->create([
            'imdb_id' => 'tt1234567',
            'tmdb_id' => '123',
            'enrichment_status' => 'enriched',
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.external_metadata.imdb_id', 'tt1234567');
        $response->assertJsonPath('data.external_metadata.tmdb_id', '123');
        $response->assertJsonPath('data.external_metadata.enrichment_status', 'enriched');
    }

    public function test_provider_priority_wins_for_conflicting_non_null_fields(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb","trakt","imdb"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.trakt.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');

        $torrent = Torrent::factory()->create();

        $enricher = new ExternalMetadataEnricher(
            app(ExternalMetadataConfig::class),
            [
                new FakeFlowExternalMetadataProvider('tmdb', new ExternalMetadataResult(provider: 'tmdb', found: true, imdbId: 'tt1111111', tmdbId: '111', title: 'TMDB Title', year: 2021, overview: 'From TMDB', externalUrl: 'https://www.themoviedb.org/movie/111')),
                new FakeFlowExternalMetadataProvider('trakt', new ExternalMetadataResult(provider: 'trakt', found: true, imdbId: 'tt2222222', tmdbId: '222', title: 'Trakt Title', year: 2022, traktId: '900', traktSlug: 'trakt-title', externalUrl: 'https://trakt.tv/movies/trakt-title')),
                new FakeFlowExternalMetadataProvider('imdb', new ExternalMetadataResult(provider: 'imdb', found: true, imdbId: 'tt3333333', title: 'IMDb Title', year: 2023, externalUrl: 'https://www.imdb.com/title/tt3333333/')),
            ]
        );

        $external = $enricher->enrich($torrent);

        $this->assertSame('tt1111111', $external->imdb_id);
        $this->assertSame('111', $external->tmdb_id);
        $this->assertSame('TMDB Title', $external->title);
        $this->assertSame(2021, $external->year);
    }

    public function test_later_provider_fills_only_missing_fields_without_overwriting_earlier_values(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb","trakt"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.trakt.enabled', 'true', 'bool');

        $torrent = Torrent::factory()->create();

        $enricher = new ExternalMetadataEnricher(
            app(ExternalMetadataConfig::class),
            [
                new FakeFlowExternalMetadataProvider('tmdb', new ExternalMetadataResult(provider: 'tmdb', found: true, imdbId: null, tmdbId: '444', title: 'Primary Title', year: 2024, overview: null, externalUrl: 'https://www.themoviedb.org/movie/444')),
                new FakeFlowExternalMetadataProvider('trakt', new ExternalMetadataResult(provider: 'trakt', found: true, imdbId: 'tt4444444', tmdbId: '999', title: 'Secondary Title', year: 2018, overview: 'From Trakt', traktSlug: 'secondary-title', externalUrl: 'https://trakt.tv/movies/secondary-title')),
            ]
        );

        $external = $enricher->enrich($torrent);

        $this->assertSame('Primary Title', $external->title);
        $this->assertSame(2024, $external->year);
        $this->assertSame('444', $external->tmdb_id);
        $this->assertSame('tt4444444', $external->imdb_id);
        $this->assertSame('From Trakt', $external->overview);
    }

    public function test_local_canonical_ids_are_preserved_and_imdb_fill_only_does_not_replace_richer_values(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb","imdb"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');

        $torrent = Torrent::factory()->create([
            'imdb_id' => 'tt7777777',
            'tmdb_id' => '777',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Local Canonical Title',
            'year' => 2020,
            'imdb_id' => 'tt7777777',
            'tmdb_id' => 777,
        ]);

        $enricher = new ExternalMetadataEnricher(
            app(ExternalMetadataConfig::class),
            [
                new FakeFlowExternalMetadataProvider('tmdb', new ExternalMetadataResult(provider: 'tmdb', found: true, imdbId: 'tt7777777', tmdbId: '777', title: 'Rich TMDB Title', year: 2020, overview: 'TMDB overview', externalUrl: 'https://www.themoviedb.org/movie/777')),
                new FakeFlowExternalMetadataProvider('imdb', new ExternalMetadataResult(provider: 'imdb', found: true, imdbId: 'tt9999999', title: 'IMDb fallback title', year: 2025, externalUrl: 'https://www.imdb.com/title/tt9999999/')),
            ]
        );

        $external = $enricher->enrich($torrent->fresh(['metadata']));

        $this->assertSame('tt7777777', $external->imdb_id);
        $this->assertSame('777', $external->tmdb_id);
        $this->assertSame('Rich TMDB Title', $external->title);
        $this->assertSame(2020, $external->year);
        $this->assertSame('TMDB overview', $external->overview);
    }

    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}

final class FakeFlowExternalMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(
        private readonly string $key,
        private readonly ExternalMetadataResult $result,
        private readonly bool $supports = true
    ) {}

    public function providerKey(): string
    {
        return $this->key;
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $this->supports;
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        return $this->result;
    }
}
