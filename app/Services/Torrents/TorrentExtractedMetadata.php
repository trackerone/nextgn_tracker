<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class TorrentExtractedMetadata
{
    public function __construct(
        public ?string $title,
        public ?string $resolution,
        public ?string $source,
        public ?string $releaseGroup,
        public ?string $imdbId,
        public ?string $imdbUrl,
        public ?string $tmdbId,
        public ?string $tmdbUrl,
        public ?string $rawNfo,
    ) {}

    public static function empty(): self
    {
        return new self(
            title: null,
            resolution: null,
            source: null,
            releaseGroup: null,
            imdbId: null,
            imdbUrl: null,
            tmdbId: null,
            tmdbUrl: null,
            rawNfo: null,
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
            'resolution' => $this->resolution,
            'source' => $this->source,
            'release_group' => $this->releaseGroup,
            'imdb_id' => $this->imdbId,
            'imdb_url' => $this->imdbUrl,
            'tmdb_id' => $this->tmdbId,
            'tmdb_url' => $this->tmdbUrl,
            'raw_nfo' => $this->rawNfo,
        ], static fn (?string $value): bool => $value !== null);
    }
}
