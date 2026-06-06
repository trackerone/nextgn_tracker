<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentBrowseFilters
{
    public function __construct(
        public readonly string $q,
        public readonly string $type,
        public readonly string $releaseGroup,
        public readonly string $language,
        public readonly string $audioLanguage,
        public readonly string $subtitleLanguage,
        public readonly string $resolution,
        public readonly string $source,
        public readonly ?int $year,
        public readonly ?int $categoryId,
        public readonly string $order,
        public readonly string $direction,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $q = trim((string) ($input['q'] ?? ''));
        $type = trim((string) ($input['type'] ?? ''));
        $releaseGroup = self::normalizeUppercase($input['release_group'] ?? '');
        $language = self::normalizeLowercase($input['language'] ?? '');
        $audioLanguage = self::normalizeLowercase($input['audio_language'] ?? '');
        $subtitleLanguage = self::normalizeCommaSeparatedLowercase($input['subtitle_language'] ?? '');
        $resolution = self::normalizeLowercase($input['resolution'] ?? '');
        $source = self::normalizeUppercase($input['source'] ?? '');

        $yearRaw = $input['year'] ?? null;
        $year = null;

        if ($yearRaw !== null && $yearRaw !== '' && is_numeric($yearRaw)) {
            $candidateYear = (int) $yearRaw;

            if ($candidateYear >= 1900 && $candidateYear <= 2100) {
                $year = $candidateYear;
            }
        }

        $sort = trim((string) ($input['sort'] ?? ''));
        $order = trim((string) ($input['order'] ?? $sort));
        $direction = strtolower(trim((string) ($input['direction'] ?? 'desc')));

        $categoryRaw = $input['category'] ?? $input['category_id'] ?? null;
        $categoryId = null;

        if ($categoryRaw !== null && $categoryRaw !== '' && is_numeric($categoryRaw)) {
            $categoryId = (int) $categoryRaw;
        }

        if ($order === '') {
            $order = 'uploaded_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return new self(
            $q,
            $type,
            $releaseGroup,
            $language,
            $audioLanguage,
            $subtitleLanguage,
            $resolution,
            $source,
            $year,
            $categoryId,
            $order,
            $direction
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'type' => $this->type,
            'release_group' => $this->releaseGroup,
            'language' => $this->language,
            'audio_language' => $this->audioLanguage,
            'subtitle_language' => $this->subtitleLanguage,
            'resolution' => $this->resolution,
            'source' => $this->source,
            'year' => $this->year,
            'category_id' => $this->categoryId,
            'order' => $this->order,
            'direction' => $this->direction,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function queryParams(): array
    {
        $params = [];

        if ($this->q !== '') {
            $params['q'] = $this->q;
        }

        if ($this->type !== '') {
            $params['type'] = $this->type;
        }

        if ($this->releaseGroup !== '') {
            $params['release_group'] = $this->releaseGroup;
        }

        if ($this->language !== '') {
            $params['language'] = $this->language;
        }

        if ($this->audioLanguage !== '') {
            $params['audio_language'] = $this->audioLanguage;
        }

        if ($this->subtitleLanguage !== '') {
            $params['subtitle_language'] = $this->subtitleLanguage;
        }

        if ($this->resolution !== '') {
            $params['resolution'] = $this->resolution;
        }

        if ($this->source !== '') {
            $params['source'] = $this->source;
        }

        if ($this->year !== null) {
            $params['year'] = $this->year;
        }

        if ($this->categoryId !== null) {
            $params['category_id'] = $this->categoryId;
        }

        if ($this->order !== '' && $this->order !== 'uploaded_at') {
            $params['order'] = $this->order;
        }

        if ($this->direction !== 'desc') {
            $params['direction'] = $this->direction;
        }

        return $params;
    }

    private static function normalizeUppercase(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(mb_strtoupper((string) $value));
    }

    private static function normalizeLowercase(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(mb_strtolower((string) $value));
    }

    private static function normalizeCommaSeparatedLowercase(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $parts = array_map(
            static fn (string $part): string => trim(mb_strtolower($part)),
            explode(',', (string) $value)
        );
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode(',', $parts);
    }
}
