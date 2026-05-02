<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Http\Client\Factory as HttpFactory;

final class TraktMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ExternalMetadataConfig $config,
    ) {}

    public function providerKey(): string
    {
        return 'trakt';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $this->config->providerEnabled($this->providerKey())
            && $this->config->traktClientId() !== null
            && ($lookup->imdbId !== null || $lookup->tmdbId !== null || $lookup->title !== null);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup)) {
            return ExternalMetadataResult::skipped($this->providerKey(), 'Provider disabled or missing credentials.');
        }

        $baseUrl = rtrim((string) config('metadata.trakt.base_url', 'https://api.trakt.tv'), '/');
        $clientId = (string) $this->config->traktClientId();

        try {
            $idType = $lookup->imdbId !== null ? 'imdb' : ($lookup->tmdbId !== null ? 'tmdb' : 'slug');
            $idValue = $lookup->imdbId ?? $lookup->tmdbId ?? str_replace(' ', '-', strtolower((string) $lookup->title));

            $response = $this->http->baseUrl($baseUrl)
                ->withHeaders([
                    'trakt-api-key' => $clientId,
                    'trakt-api-version' => '2',
                ])
                ->get(sprintf('/search/%s/%s', $idType, $idValue), [
                    'type' => $lookup->mediaType === 'tv' ? 'show' : 'movie',
                    'limit' => 1,
                ])
                ->throw()
                ->json();

            $first = is_array($response) ? ($response[0] ?? null) : null;
            if (! is_array($first)) {
                return ExternalMetadataResult::skipped($this->providerKey(), 'No metadata found.');
            }

            $entity = $lookup->mediaType === 'tv' ? ($first['show'] ?? null) : ($first['movie'] ?? null);
            if (! is_array($entity)) {
                $entity = $first['movie'] ?? $first['show'] ?? null;
            }

            if (! is_array($entity)) {
                return ExternalMetadataResult::skipped($this->providerKey(), 'No normalized metadata found.');
            }

            $traktId = $this->nullableString(data_get($entity, 'ids.trakt'));
            $slug = $this->nullableString(data_get($entity, 'ids.slug'));
            $imdbId = $this->nullableString(data_get($entity, 'ids.imdb'));
            $tmdbId = $this->nullableString(data_get($entity, 'ids.tmdb'));
            $mediaType = array_key_exists('show', $first) ? 'tv' : 'movie';

            return new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: true,
                imdbId: $imdbId,
                tmdbId: $tmdbId,
                traktId: $traktId,
                traktSlug: $slug,
                title: $this->nullableString($entity['title'] ?? null),
                year: isset($entity['year']) ? (int) $entity['year'] : null,
                mediaType: $mediaType,
                externalUrl: $slug !== null ? sprintf('https://trakt.tv/%s/%s', $mediaType === 'tv' ? 'shows' : 'movies', $slug) : null,
                rawPayload: $first,
            );
        } catch (\Throwable $exception) {
            return ExternalMetadataResult::skipped($this->providerKey(), $exception->getMessage());
        }
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
