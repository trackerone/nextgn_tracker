<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\Torrent;

final class TorrentModerationMetadataReview
{
    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array{needs_review: bool, issues: array<int, string>, labels: array<int, string>}
     */
    public static function evaluate(array $metadata, ?string $torrentName = null): array
    {
        $issues = [];
        $type = self::stringOrNull($metadata['type'] ?? null);

        if ($type === null) {
            $issues[] = 'missing_type';
        }

        if (self::stringOrNull($metadata['resolution'] ?? null) === null) {
            $issues[] = 'missing_resolution';
        }

        if (self::stringOrNull($metadata['source'] ?? null) === null) {
            $issues[] = 'missing_source';
        }

        if (self::isMovieLike($type) && self::yearOrNull($metadata['year'] ?? null) === null) {
            $issues[] = 'missing_year';
        }

        if (self::isTvLike($type)) {
            $subject = self::stringOrNull($metadata['title'] ?? null) ?? self::stringOrNull($torrentName);

            if (! self::hasSeasonEpisodeToken($subject)) {
                $issues[] = 'missing_season_episode';
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'needs_review' => $issues !== [],
            'issues' => $issues,
            'labels' => array_map(static fn (string $issue): string => self::labelForIssue($issue), $issues),
        ];
    }

    /**
     * @param  iterable<int, Torrent>  $torrents
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @return array<int, array{needs_review: bool, issues: array<int, string>, labels: array<int, string>}>
     */
    public static function mapByTorrentId(iterable $torrents, array $metadataByTorrentId): array
    {
        $mapped = [];

        foreach ($torrents as $torrent) {
            $mapped[$torrent->id] = self::evaluate(
                $metadataByTorrentId[$torrent->id] ?? [],
                $torrent->name
            );
        }

        return $mapped;
    }

    private static function labelForIssue(string $issue): string
    {
        return match ($issue) {
            'missing_type' => 'Missing type',
            'missing_resolution' => 'Missing resolution',
            'missing_source' => 'Missing source',
            'missing_year' => 'Missing year',
            'missing_season_episode' => 'Missing season/episode',
            default => 'Metadata issue',
        };
    }

    private static function hasSeasonEpisodeToken(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return preg_match('/\b(S\d{1,2}E\d{1,2}|\d{1,2}x\d{1,2}|Season\s*\d+\s*Episode\s*\d+)\b/i', $value) === 1;
    }

    private static function isMovieLike(?string $type): bool
    {
        return $type === 'movie' || $type === 'documentary';
    }

    private static function isTvLike(?string $type): bool
    {
        return in_array($type, ['tv', 'series', 'episode'], true);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function yearOrNull(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
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
