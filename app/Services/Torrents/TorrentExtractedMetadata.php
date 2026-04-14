<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class TorrentExtractedMetadata
{
    public function __construct(
        public ?string $title,
        public ?string $year,
        public ?string $resolution,
        public ?string $source,
        public ?string $releaseGroup,
        public ?string $imdbId,
        public ?string $imdbUrl,
        public ?string $tmdbId,
        public ?string $tmdbUrl,
        public ?string $rawNfo,
        public ?string $rawName = null,
        public ?string $parsedName = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            title: null,
            year: null,
            resolution: null,
            source: null,
            releaseGroup: null,
            imdbId: null,
            imdbUrl: null,
            tmdbId: null,
            tmdbUrl: null,
            rawNfo: null,
            rawName: null,
            parsedName: null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'year' => $this->year,
            'resolution' => $this->resolution,
            'source' => $this->source,
            'release_group' => $this->releaseGroup,
            'imdb_id' => $this->imdbId,
            'imdb_url' => $this->imdbUrl,
            'tmdb_id' => $this->tmdbId,
            'tmdb_url' => $this->tmdbUrl,
            'raw_nfo' => $this->rawNfo,
            'raw_name' => $this->rawName,
            'parsed_name' => $this->parsedName,
        ], static fn (?string $value): bool => $value !== null);
    }
}
