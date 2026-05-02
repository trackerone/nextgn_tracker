<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Http\Client\Factory as HttpFactory;

final class TmdbMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ExternalMetadataConfig $config,
    ) {}

    public function providerKey(): string
    {
        return 'tmdb';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $this->config->providerEnabled($this->providerKey())
            && $this->config->tmdbApiKey() !== null
            && ($lookup->imdbId !== null || $lookup->title !== null || $lookup->tmdbId !== null);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup)) {
            return ExternalMetadataResult::skipped($this->providerKey(), 'Provider disabled or missing credentials.');
        }

        $apiKey = (string) $this->config->tmdbApiKey();
        $baseUrl = rtrim((string) config('metadata.tmdb.base_url', 'https://api.themoviedb.org/3'), '/');
        $imageBaseUrl = rtrim((string) config('metadata.tmdb.image_base_url', 'https://image.tmdb.org/t/p/original'), '/');

        try {
            $payload = null;

            if ($lookup->imdbId !== null) {
                $find = $this->http->baseUrl($baseUrl)
                    ->get(sprintf('/find/%s', $lookup->imdbId), [
                        'api_key' => $apiKey,
                        'external_source' => 'imdb_id',
                    ])
                    ->throw()
                    ->json();

                $payload = $this->firstTmdbFindResult($find);
            }

            if ($payload === null && $lookup->title !== null) {
                $searchPath = $lookup->mediaType === 'tv' ? '/search/tv' : '/search/movie';
                $search = $this->http->baseUrl($baseUrl)
                    ->get($searchPath, [
                        'api_key' => $apiKey,
                        'query' => $lookup->title,
                        'year' => $lookup->year,
                    ])
                    ->throw()
                    ->json();

                $payload = data_get($search, 'results.0');
            }

            if (! is_array($payload)) {
                return ExternalMetadataResult::skipped($this->providerKey(), 'No metadata found.');
            }

            $posterPath = $this->nullableString($payload['poster_path'] ?? null);
            $backdropPath = $this->nullableString($payload['backdrop_path'] ?? null);
            $mediaType = $this->normalizeMediaType($payload['media_type'] ?? $lookup->mediaType ?? 'unknown');
            $tmdbId = $this->nullableString($payload['id'] ?? null);

            return new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: true,
                imdbId: $lookup->imdbId,
                tmdbId: $tmdbId,
                title: $this->nullableString($payload['title'] ?? $payload['name'] ?? null),
                originalTitle: $this->nullableString($payload['original_title'] ?? $payload['original_name'] ?? null),
                year: $this->extractYear($payload['release_date'] ?? $payload['first_air_date'] ?? null),
                mediaType: $mediaType,
                overview: $this->nullableString($payload['overview'] ?? null),
                posterPath: $posterPath,
                posterUrl: $posterPath !== null ? $imageBaseUrl.$posterPath : null,
                backdropPath: $backdropPath,
                backdropUrl: $backdropPath !== null ? $imageBaseUrl.$backdropPath : null,
                externalUrl: $tmdbId !== null ? sprintf('https://www.themoviedb.org/%s/%s', $mediaType === 'tv' ? 'tv' : 'movie', $tmdbId) : null,
                rawPayload: $payload,
            );
        } catch (\Throwable $exception) {
            return ExternalMetadataResult::skipped($this->providerKey(), $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $payload */
    private function firstTmdbFindResult(array $payload): ?array
    {
        foreach (['movie_results', 'tv_results', 'tv_episode_results'] as $key) {
            $result = data_get($payload, $key.'.0');
            if (is_array($result)) {
                return $result;
            }
        }

        return null;
    }

    private function normalizeMediaType(string $mediaType): string
    {
        return match ($mediaType) {
            'movie', 'tv', 'episode' => $mediaType,
            'tv_episode' => 'episode',
            default => 'unknown',
        };
    }

    private function extractYear(mixed $date): ?int
    {
        if (! is_string($date) || strlen($date) < 4) {
            return null;
        }

        return (int) substr($date, 0, 4);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $result = trim((string) $value);

        return $result === '' ? null : $result;
    }
}
