<?php

declare(strict_types=1);

namespace App\Services\Metadata\DTO;

final readonly class ExternalMetadataLookup
{
    public function __construct(
        public ?string $imdbId,
        public ?string $tmdbId,
        public ?string $traktId,
        public ?string $title,
        public ?int $year,
        public ?string $mediaType,
    ) {}
}
