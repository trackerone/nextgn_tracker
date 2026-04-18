<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\Torrent;

final class TorrentMetadataQuality
{
    private const ISSUE_DEFINITIONS = [
        'missing_type' => ['label' => 'Missing type', 'field' => 'type', 'penalty' => 30, 'severity' => 'critical'],
        'missing_resolution' => ['label' => 'Missing resolution', 'field' => 'resolution', 'penalty' => 25, 'severity' => 'critical'],
        'missing_source' => ['label' => 'Missing source', 'field' => 'source', 'penalty' => 25, 'severity' => 'critical'],
        'missing_year' => ['label' => 'Missing year', 'field' => 'year', 'penalty' => 10, 'severity' => 'warning'],
        'missing_season_episode' => ['label' => 'Missing season/episode', 'field' => 'season_episode', 'penalty' => 10, 'severity' => 'warning'],
    ];

    /** @param array<string, int|string|null> $metadata */
    public static function evaluate(array $metadata, ?string $torrentName = null): array
    {
        $issues = self::issuesFor($metadata, $torrentName);

        return [
            'score' => self::scoreForIssues($issues),
            'completeness' => self::completenessLevelForIssues($issues),
            'review_category' => self::reviewCategoryForIssues($issues),
            'missing_fields' => array_values(array_map(static fn (string $issue): string => self::ISSUE_DEFINITIONS[$issue]['field'], $issues)),
            'issues' => $issues,
            'labels' => array_values(array_map(static fn (string $issue): string => self::ISSUE_DEFINITIONS[$issue]['label'], $issues)),
        ];
    }

    /** @param iterable<int, Torrent> $torrents @param array<int, array<string, int|string|null>> $metadataByTorrentId */
    public static function mapByTorrentId(iterable $torrents, array $metadataByTorrentId): array
    {
        $mapped = [];

        foreach ($torrents as $torrent) {
            $mapped[$torrent->id] = self::evaluate($metadataByTorrentId[$torrent->id] ?? [], $torrent->name);
        }

        return $mapped;
    }

    /** @param array<string, int|string|null> $metadata @return array<int, string> */
    private static function issuesFor(array $metadata, ?string $torrentName): array
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

        return array_values(array_unique($issues));
    }

    /** @param array<int, string> $issues */
    private static function scoreForIssues(array $issues): int
    {
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += self::ISSUE_DEFINITIONS[$issue]['penalty'];
        }

        return max(0, 100 - $penalty);
    }

    /** @param array<int, string> $issues */
    private static function completenessLevelForIssues(array $issues): string
    {
        $score = self::scoreForIssues($issues);

        return match (true) {
            $score >= 90 => 'high',
            $score >= 70 => 'medium',
            default => 'low',
        };
    }

    /** @param array<int, string> $issues */
    private static function reviewCategoryForIssues(array $issues): string
    {
        if ($issues === []) {
            return 'ok';
        }

        foreach ($issues as $issue) {
            if (self::ISSUE_DEFINITIONS[$issue]['severity'] === 'critical') {
                return 'critical';
            }
        }

        return 'warning';
    }

    private static function hasSeasonEpisodeToken(?string $value): bool
    {
        return $value !== null
            && preg_match('/\b(S\d{1,2}E\d{1,2}|\d{1,2}x\d{1,2}|Season\s*\d+\s*Episode\s*\d+)\b/i', $value) === 1;
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

        return $trimmed !== '' && ctype_digit($trimmed) ? (int) $trimmed : null;
    }
}
