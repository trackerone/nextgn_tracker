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
            && $this->isValidImdbId($lookup->imdbId);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->isValidImdbId($lookup->imdbId)) {
            return ExternalMetadataResult::skipped($this->providerKey(), 'No IMDb identifier available.');
        }

        if (! $this->config->providerEnabled($this->providerKey())) {
            return ExternalMetadataResult::skipped($this->providerKey(), 'Provider disabled.');
        }

        $imdbId = strtolower((string) $lookup->imdbId);

        return new ExternalMetadataResult(
            provider: $this->providerKey(),
            found: true,
            imdbId: $imdbId,
            externalUrl: sprintf('https://www.imdb.com/title/%s/', $imdbId),
            rawPayload: [
                'source' => 'imdb',
                'mode' => 'fill_only',
            ],
        );
    }

    private function isValidImdbId(?string $imdbId): bool
    {
        if (! is_string($imdbId)) {
            return false;
        }

        return preg_match('/^tt\d{7,8}$/i', trim($imdbId)) === 1;
    }
}
