<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentMetadataPresenter
{
    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array<int, array{label: string, value: string}>
     */
    public static function detailFacts(array $metadata): array
    {
        $rows = [
            self::fact('Type', self::formatType($metadata['type'] ?? null)),
            self::fact('Resolution', self::formatResolution($metadata['resolution'] ?? null)),
            self::fact('Source', self::formatText($metadata['source'] ?? null)),
            self::fact('Release group', self::formatText($metadata['release_group'] ?? null)),
            self::fact('Year', self::formatYear($metadata['year'] ?? null)),
        ];

        return array_values(array_filter($rows));
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array<int, string>
     */
    public static function listingBadges(array $metadata): array
    {
        $badges = [
            self::formatType($metadata['type'] ?? null),
            self::formatResolution($metadata['resolution'] ?? null),
            self::formatText($metadata['source'] ?? null),
            self::formatText($metadata['release_group'] ?? null),
            self::formatYear($metadata['year'] ?? null),
        ];

        return array_values(array_filter($badges));
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    public static function typeLabel(array $metadata): ?string
    {
        return self::formatType($metadata['type'] ?? null);
    }

    private static function fact(string $label, ?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return ['label' => $label, 'value' => $value];
    }

    private static function formatType(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? ucfirst(strtolower($normalized)) : null;
    }

    private static function formatText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? strtoupper($normalized) : null;
    }

    private static function formatResolution(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? strtolower($normalized) : null;
    }

    private static function formatYear(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }
}
