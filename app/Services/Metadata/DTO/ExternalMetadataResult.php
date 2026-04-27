<?php

declare(strict_types=1);

namespace App\Services\Metadata\DTO;

final readonly class ExternalMetadataResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public bool $found,
        public ?string $imdbId = null,
        public ?string $tmdbId = null,
        public ?string $traktId = null,
        public ?string $traktSlug = null,
        public ?string $title = null,
        public ?string $originalTitle = null,
        public ?int $year = null,
        public ?string $mediaType = null,
        public ?string $overview = null,
        public ?string $posterPath = null,
        public ?string $posterUrl = null,
        public ?string $backdropPath = null,
        public ?string $backdropUrl = null,
        public ?string $externalUrl = null,
        public array $rawPayload = [],
        public ?string $error = null,
    ) {}

    public static function skipped(string $provider, ?string $error = null): self
    {
        return new self(provider: $provider, found: false, error: $error);
    }
}
