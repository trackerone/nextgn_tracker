<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;

final class ImdbMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(private readonly ExternalMetadataConfig $config) {}

    public function providerKey(): string
    {
        return 'imdb';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $this->config->providerEnabled($this->providerKey())
            && (bool) config('metadata.imdb.dataset_enabled', false)
            && $lookup->imdbId !== null;
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if ($lookup->imdbId !== null && str_starts_with($lookup->imdbId, 'tt')) {
            return new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: false,
                imdbId: $lookup->imdbId,
                externalUrl: sprintf('https://www.imdb.com/title/%s/', $lookup->imdbId),
                error: $this->supports($lookup) ? 'IMDb official integration TODO: not implemented yet.' : 'Provider disabled or credentials unavailable.',
            );
        }

        return ExternalMetadataResult::skipped($this->providerKey(), 'No IMDb identifier available.');
    }
}
