<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class CanonicalTorrentMetadata
{
    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    private function __construct(
        public ?string $title,
        public ?int $year,
        public ?string $type,
        public ?string $resolution,
        public ?string $source,
        public ?string $releaseGroup,
        public ?string $imdbId,
        public ?string $imdbUrl,
        public ?int $tmdbId,
        public ?string $tmdbUrl,
        public ?string $nfo,
        public ?string $rawName,
        public ?string $parsedName,
        public ?array $rawPayload,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $imdbId = self::normalizeImdbId(self::normalizeString($attributes['imdb_id'] ?? null));
        $tmdbId = self::normalizeTmdbId($attributes['tmdb_id'] ?? null);

        return new self(
            title: self::normalizeString($attributes['title'] ?? null),
            year: self::normalizeYear($attributes['year'] ?? null),
            type: self::normalizeString($attributes['type'] ?? null),
            resolution: self::normalizeString($attributes['resolution'] ?? null),
            source: self::normalizeString($attributes['source'] ?? null),
            releaseGroup: self::normalizeReleaseGroup(self::normalizeString($attributes['release_group'] ?? null)),
            imdbId: $imdbId,
            imdbUrl: self::imdbUrl($imdbId),
            tmdbId: $tmdbId,
            tmdbUrl: self::tmdbUrl($tmdbId),
            nfo: self::normalizeString($attributes['nfo'] ?? null),
            rawName: self::normalizeString($attributes['raw_name'] ?? null),
            parsedName: self::normalizeString($attributes['parsed_name'] ?? null),
            rawPayload: is_array($attributes['raw_payload'] ?? null) ? $attributes['raw_payload'] : null,
        );
    }

    public static function fromExtractedMetadata(
        TorrentExtractedMetadata $metadata,
        ?string $type = null,
        ?string $resolution = null,
        ?string $source = null,
    ): self {
        return self::fromArray([
            'title' => $metadata->title,
            'year' => $metadata->year,
            'type' => $type,
            'resolution' => $metadata->resolution ?? $resolution,
            'source' => $metadata->source ?? $source,
            'release_group' => $metadata->releaseGroup,
            'imdb_id' => $metadata->imdbId,
            'tmdb_id' => $metadata->tmdbId,
            'nfo' => $metadata->rawNfo,
            'raw_name' => $metadata->rawName,
            'parsed_name' => $metadata->parsedName,
            'raw_payload' => $metadata->toArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        return [
            'title' => $this->title,
            'year' => $this->year,
            'type' => $this->type,
            'resolution' => $this->resolution,
            'source' => $this->source,
            'release_group' => $this->releaseGroup,
            'imdb_id' => $this->imdbId,
            'imdb_url' => $this->imdbUrl,
            'tmdb_id' => $this->tmdbId,
            'tmdb_url' => $this->tmdbUrl,
            'nfo' => $this->nfo,
            'raw_name' => $this->rawName,
            'parsed_name' => $this->parsedName,
            'raw_payload' => $this->rawPayload,
        ];
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeImdbId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/tt\d{7,8}/i', $value, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[0]);
    }

    private static function normalizeTmdbId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        return (int) $digits;
    }

    private static function normalizeYear(mixed $value): ?int
    {
        if (is_int($value) && $value >= 1900 && $value <= 2100) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^(19|20)\d{2}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private static function normalizeReleaseGroup(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/^[\-\[\]()\s]+|[\-\[\]()\s]+$/', '', $value) ?? $value;

        return $normalized === '' ? null : $normalized;
    }

    private static function imdbUrl(?string $imdbId): ?string
    {
        return $imdbId !== null ? sprintf('https://www.imdb.com/title/%s/', $imdbId) : null;
    }

    private static function tmdbUrl(?int $tmdbId): ?string
    {
        return $tmdbId !== null ? sprintf('https://www.themoviedb.org/movie/%d', $tmdbId) : null;
    }
}
