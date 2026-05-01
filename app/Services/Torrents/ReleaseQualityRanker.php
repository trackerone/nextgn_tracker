<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use Illuminate\Support\Str;

final class ReleaseQualityRanker
{
    /**
     * @param  array<string, int|string|null>  $metadata
     */
    public function score(array $metadata): int
    {
        return $this->resolutionScore($metadata)
            + $this->sourceScore($metadata)
            + $this->completenessBonus($metadata)
            + $this->externalIdBonus($metadata)
            + $this->cleanTitleYearBonus($metadata)
            + $this->highCompletenessBonus($metadata)
            + $this->missingReleaseGroupPenalty($metadata);
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function resolutionScore(array $metadata): int
    {
        $resolution = Str::upper((string) ($this->stringOrNull($metadata['resolution'] ?? null) ?? ''));

        return match ($resolution) {
            '2160P' => 400,
            '1080P' => 300,
            '720P' => 200,
            default => 100,
        };
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function sourceScore(array $metadata): int
    {
        $source = Str::upper((string) ($this->stringOrNull($metadata['source'] ?? null) ?? ''));

        return match ($source) {
            'BLURAY', 'BLU-RAY' => 500,
            'WEB-DL', 'WEBDL' => 400,
            'WEBRIP' => 300,
            'HDTV' => 200,
            default => 100,
        };
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function completenessBonus(array $metadata): int
    {
        $bonus = 0;

        if ($this->stringOrNull($metadata['title'] ?? null) !== null) {
            $bonus += 5;
        }

        if ($this->intOrNull($metadata['year'] ?? null) !== null) {
            $bonus += 5;
        }

        if ($this->stringOrNull($metadata['resolution'] ?? null) !== null) {
            $bonus += 5;
        }

        if ($this->stringOrNull($metadata['source'] ?? null) !== null) {
            $bonus += 5;
        }

        if ($this->stringOrNull($metadata['imdb_id'] ?? null) !== null) {
            $bonus += 5;
        }

        if ($this->intOrNull($metadata['tmdb_id'] ?? null) !== null) {
            $bonus += 5;
        }

        return $bonus;
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function externalIdBonus(array $metadata): int
    {
        $hasExternalId = $this->stringOrNull($metadata['imdb_id'] ?? null) !== null
            || $this->intOrNull($metadata['tmdb_id'] ?? null) !== null;

        return $hasExternalId ? 8 : 0;
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function cleanTitleYearBonus(array $metadata): int
    {
        $hasTitleYear = $this->stringOrNull($metadata['title'] ?? null) !== null
            && $this->intOrNull($metadata['year'] ?? null) !== null;

        return $hasTitleYear ? 6 : 0;
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function highCompletenessBonus(array $metadata): int
    {
        $trackedFields = [
            'title',
            'year',
            'resolution',
            'source',
            'release_group',
            'imdb_id',
            'tmdb_id',
        ];

        $missing = 0;

        foreach ($trackedFields as $field) {
            $value = $metadata[$field] ?? null;
            $present = $field === 'year' || $field === 'tmdb_id'
                ? $this->intOrNull($value) !== null
                : $this->stringOrNull($value) !== null;

            if (! $present) {
                $missing++;
            }
        }

        return $missing <= 1 ? 8 : ($missing <= 2 ? 4 : 0);
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function missingReleaseGroupPenalty(array $metadata): int
    {
        return $this->stringOrNull($metadata['release_group'] ?? null) === null ? -3 : 0;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }
}
