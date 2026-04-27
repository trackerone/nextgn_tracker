<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\Providers\ImdbMetadataProvider;
use App\Services\Metadata\Providers\TmdbMetadataProvider;
use App\Services\Metadata\Providers\TraktMetadataProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class ExternalMetadataEnricher
{
    /**
     * @var array<string, ExternalMetadataProvider>
     */
    private array $providers;

    public function __construct(
        private readonly ExternalMetadataConfig $config,
        TmdbMetadataProvider $tmdbProvider,
        TraktMetadataProvider $traktProvider,
        ImdbMetadataProvider $imdbProvider,
    ) {
        $this->providers = [
            $tmdbProvider->providerKey() => $tmdbProvider,
            $traktProvider->providerKey() => $traktProvider,
            $imdbProvider->providerKey() => $imdbProvider,
        ];
    }

    public function enrich(Torrent $torrent): TorrentExternalMetadata
    {
        $externalMetadata = $torrent->externalMetadata()->firstOrNew();

        if (! $this->config->enrichmentEnabled()) {
            $externalMetadata->fill([
                'enrichment_status' => 'skipped',
                'last_error' => 'External metadata enrichment is disabled.',
            ])->save();

            return $externalMetadata;
        }

        $lookup = $this->buildLookup($torrent);
        $merged = [];
        $payloads = [];
        $errors = [];
        $useful = false;
        $unexpectedFailure = false;

        foreach ($this->config->providerPriority() as $providerKey) {
            $provider = $this->providers[$providerKey] ?? null;
            if (! $provider instanceof ExternalMetadataProvider) {
                continue;
            }

            if (! $provider->supports($lookup)) {
                continue;
            }

            try {
                $result = $provider->lookup($lookup);
            } catch (\Throwable $exception) {
                $unexpectedFailure = true;
                $errors[] = sprintf('%s: %s', $providerKey, $exception->getMessage());
                Log::warning('External metadata provider lookup failed unexpectedly.', [
                    'provider' => $providerKey,
                    'torrent_id' => $torrent->id,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }
            $payloads[$providerKey] = $result->rawPayload;

            if ($result->error !== null) {
                $errors[] = sprintf('%s: %s', $providerKey, $result->error);
            }

            if ($result->found) {
                $useful = true;
            }

            $merged = $this->mergeResult($merged, $result);
        }

        $status = $useful ? 'enriched' : ($unexpectedFailure ? 'failed' : 'skipped');
        $externalMetadata->fill([
            'torrent_id' => $torrent->id,
            ...$merged,
            'providers_payload' => $payloads === [] ? null : $payloads,
            'enriched_at' => Carbon::now(),
            'enrichment_status' => $status,
            'last_error' => $errors === [] ? null : implode('; ', $errors),
        ])->save();

        return $externalMetadata;
    }

    private function buildLookup(Torrent $torrent): ExternalMetadataLookup
    {
        $canonical = $torrent->metadata;

        return new ExternalMetadataLookup(
            imdbId: $canonical?->imdb_id ?? $torrent->imdb_id,
            tmdbId: ($canonical?->tmdb_id !== null ? (string) $canonical->tmdb_id : null) ?? ($torrent->tmdb_id !== null ? (string) $torrent->tmdb_id : null),
            traktId: null,
            title: $canonical?->title ?? $torrent->name,
            year: $canonical?->year,
            mediaType: $canonical?->type ?? $torrent->type,
        );
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeResult(array $merged, ExternalMetadataResult $result): array
    {
        $candidate = [
            'imdb_id' => $result->imdbId,
            'tmdb_id' => $result->tmdbId,
            'trakt_id' => $result->traktId,
            'trakt_slug' => $result->traktSlug,
            'title' => $result->title,
            'original_title' => $result->originalTitle,
            'year' => $result->year,
            'media_type' => $result->mediaType,
            'overview' => $result->overview,
            'poster_path' => $result->posterPath,
            'poster_url' => $result->posterUrl,
            'backdrop_path' => $result->backdropPath,
            'backdrop_url' => $result->backdropUrl,
            'imdb_url' => $result->imdbId !== null ? sprintf('https://www.imdb.com/title/%s/', $result->imdbId) : null,
            'tmdb_url' => $result->provider === 'tmdb' ? $result->externalUrl : ($merged['tmdb_url'] ?? null),
            'trakt_url' => $result->provider === 'trakt' ? $result->externalUrl : ($merged['trakt_url'] ?? null),
        ];

        foreach ($candidate as $key => $value) {
            if (($merged[$key] ?? null) === null && $value !== null) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
