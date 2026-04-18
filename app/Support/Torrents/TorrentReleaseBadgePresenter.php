<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentReleaseBadgePresenter
{
    private const CRITICAL_MISSING_FIELDS = ['type', 'resolution', 'source'];

    /**
     * @param  array{
     *     completeness?: string,
     *     missing_fields?: array<int, string>
     * }  $metadataQuality
     * @return array<int, string>
     */
    public static function browseBadges(array $metadataQuality, bool $recommended): array
    {
        $badges = [];

        if ($recommended) {
            $badges[] = 'Recommended';
        }

        $badges[] = self::qualityLabel((string) ($metadataQuality['completeness'] ?? 'low'));

        if (self::hasCriticalMissingFields($metadataQuality['missing_fields'] ?? [])) {
            $badges[] = 'Incomplete metadata';
        }

        return $badges;
    }

    private static function qualityLabel(string $completeness): string
    {
        return match ($completeness) {
            'high' => 'High quality',
            'medium' => 'Medium quality',
            default => 'Low quality',
        };
    }

    /** @param array<int, string> $missingFields */
    private static function hasCriticalMissingFields(array $missingFields): bool
    {
        return array_intersect($missingFields, self::CRITICAL_MISSING_FIELDS) !== [];
    }
}
