<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata;

use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Metadata\ExternalMetadataEnricher;
use App\Services\Metadata\Providers\ImdbMetadataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExternalMetadataEnricherTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrichment_disabled_marks_record_as_skipped(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'false', 'bool');

        $torrent = Torrent::factory()->create();
        $enricher = app(ExternalMetadataEnricher::class);

        $record = $enricher->enrich($torrent);

        $this->assertSame('skipped', $record->enrichment_status);
    }

    public function test_provider_priority_is_respected_and_tmdb_is_persisted(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb","trakt","imdb"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.trakt.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');

        $config = app(ExternalMetadataConfig::class);

        $this->assertSame(['tmdb', 'trakt', 'imdb'], $config->providerPriority());

        $tmdb = new FakeExternalMetadataProvider(
            key: 'tmdb',
            result: new ExternalMetadataResult(
                provider: 'tmdb',
                found: true,
                imdbId: 'tt0111161',
                tmdbId: '278',
                title: 'The Shawshank Redemption',
                year: 1994,
                mediaType: 'movie',
                externalUrl: 'https://www.themoviedb.org/movie/278',
                rawPayload: ['id' => 278],
            ),
        );
        $trakt = new FakeExternalMetadataProvider(
            key: 'trakt',
            result: new ExternalMetadataResult(
                provider: 'trakt',
                found: true,
                traktId: '1',
                traktSlug: 'shawshank-redemption-1994',
                externalUrl: 'https://trakt.tv/movies/shawshank-redemption-1994',
                rawPayload: ['ids' => ['trakt' => 1]],
            ),
        );
        $imdb = new FakeExternalMetadataProvider(
            key: 'imdb',
            result: ExternalMetadataResult::skipped('imdb'),
            supports: false,
        );

        $torrent = Torrent::factory()->create(['imdb_id' => 'tt0111161']);
        $enricher = new ExternalMetadataEnricher($config, [$tmdb, $trakt, $imdb]);

        $record = $enricher->enrich($torrent);

        $this->assertSame(1, $tmdb->lookupCalls);
        $this->assertSame(1, $trakt->lookupCalls);
        $this->assertSame(0, $imdb->lookupCalls);
        $this->assertSame('enriched', $record->enrichment_status);
        $this->assertSame('tt0111161', $record->imdb_id);
        $this->assertSame('278', $record->tmdb_id);
        $this->assertSame('1', $record->trakt_id);
        $this->assertSame('https://www.themoviedb.org/movie/278', $record->tmdb_url);
        $this->assertSame('https://trakt.tv/movies/shawshank-redemption-1994', $record->trakt_url);
    }

    public function test_imdb_provider_without_credentials_safely_skips(): void
    {
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');
        config()->set('metadata.imdb.dataset_enabled', false);

        $provider = app(ImdbMetadataProvider::class);
        $result = $provider->lookup(new ExternalMetadataLookup('tt1234567', null, null, null, null, 'movie'));

        $this->assertFalse($result->found);
        $this->assertSame('imdb', $result->provider);
        $this->assertSame('tt1234567', $result->imdbId);
        $this->assertSame('https://www.imdb.com/title/tt1234567/', $result->externalUrl);
    }

    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }
}

final class FakeExternalMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(
        private readonly string $key,
        private readonly ExternalMetadataResult $result,
        private readonly bool $supports = true,
    ) {
    }

    public int $lookupCalls = 0;

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
        $this->lookupCalls++;

        return $this->result;
    }
}
