<?php

declare(strict_types=1);

namespace App\Services\Settings;

final class UploadLimits
{
    /**
     * Upload limits are configurable by Sysop, but deliberately bounded.
     * MIME types, extensions, destinations and PHP/web-server hard limits are not editable here.
     *
     * @var array<string, array{key: string, config: string, fallback: int, min: int, max: int}>
     */
    public const DEFINITIONS = [
        'torrent_max_kb' => [
            'key' => 'uploads.torrents.max_kilobytes',
            'config' => 'upload.torrents.max_kilobytes',
            'fallback' => 1024,
            'min' => 64,
            'max' => 8192,
        ],
        'nfo_max_kb' => [
            'key' => 'uploads.nfo.max_kilobytes',
            'config' => 'upload.nfo.max_kilobytes',
            'fallback' => 256,
            'min' => 16,
            'max' => 2048,
        ],
        'nfo_max_characters' => [
            'key' => 'uploads.nfo.max_characters',
            'config' => 'upload.nfo.max_characters',
            'fallback' => 262144,
            'min' => 1024,
            'max' => 1048576,
        ],
    ];

    public function __construct(private readonly SiteSettingsRepository $settings) {}

    /**
     * One settings query per caller rather than a database lookup per file rule.
     *
     * @return array{torrent_max_kb: int, nfo_max_kb: int, nfo_max_characters: int}
     */
    public function values(): array
    {
        $stored = $this->settings->getMany(array_column(self::DEFINITIONS, 'key'));
        $result = [];

        foreach (self::DEFINITIONS as $field => $definition) {
            $fallback = (int) config($definition['config'], $definition['fallback']);
            $raw = $stored[$definition['key']] ?? $fallback;
            $value = filter_var($raw, FILTER_VALIDATE_INT);

            $result[$field] = min(
                $definition['max'],
                max($definition['min'], $value === false ? $fallback : $value)
            );
        }

        /** @var array{torrent_max_kb: int, nfo_max_kb: int, nfo_max_characters: int} $result */
        return $result;
    }
}
