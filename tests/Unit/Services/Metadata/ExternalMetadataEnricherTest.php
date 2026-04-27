<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata;

use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataEnricher;
use App\Services\Metadata\Providers\ImdbMetadataProvider;
use App\Services\Metadata\Providers\TmdbMetadataProvider;
use App\Services\Metadata\Providers\TraktMetadataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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
        $this->setSiteSetting('metadata.providers.priority', '["tmdb","trakt"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.trakt.enabled', 'true', 'bool');

        $tmdb = Mockery::mock(TmdbMetadataProvider::class);
        $trakt = Mockery::mock(TraktMetadataProvider::class);
        $imdb = Mockery::mock(ImdbMetadataProvider::class);

        $tmdb->shouldReceive('providerKey')->andReturn('tmdb');
        $tmdb->shouldReceive('supports')->andReturn(true);
        $tmdb->shouldReceive('lookup')->once()->andReturn(new ExternalMetadataResult(
            provider: 'tmdb',
            found: true,
            imdbId: 'tt0111161',
            tmdbId: '278',
            title: 'The Shawshank Redemption',
            year: 1994,
            mediaType: 'movie',
            externalUrl: 'https://www.themoviedb.org/movie/278',
            rawPayload: ['id' => 278],
        ));

        $trakt->shouldReceive('providerKey')->andReturn('trakt');
        $trakt->shouldReceive('supports')->andReturn(true);
        $trakt->shouldReceive('lookup')->once()->andReturn(new ExternalMetadataResult(
            provider: 'trakt',
            found: true,
            traktId: '1',
            traktSlug: 'shawshank-redemption-1994',
            externalUrl: 'https://trakt.tv/movies/shawshank-redemption-1994',
            rawPayload: ['ids' => ['trakt' => 1]],
        ));

        $imdb->shouldReceive('providerKey')->andReturn('imdb');

        $torrent = Torrent::factory()->create(['imdb_id' => 'tt0111161']);
        $enricher = app()->makeWith(ExternalMetadataEnricher::class, [
            'tmdbProvider' => $tmdb,
            'traktProvider' => $trakt,
            'imdbProvider' => $imdb,
        ]);

        $record = $enricher->enrich($torrent);

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
