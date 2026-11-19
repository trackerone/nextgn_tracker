<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Support\Uploads\UploadTextEncoding;
use InvalidArgumentException;

final class TorrentPayloadFiles
{
    /**
     * @param  array<string, mixed>  $info
     * @return array{count: int, size: int}
     */
    public static function stats(array $info): array
    {
        if (isset($info['length']) && is_numeric($info['length'])) {
            return ['count' => 1, 'size' => (int) $info['length']];
        }

        $files = $info['files'] ?? null;
        if (! is_array($files) || $files === []) {
            throw new InvalidArgumentException('Torrent files metadata is missing.');
        }

        $count = 0;
        $size = 0;

        foreach ($files as $file) {
            if (! is_array($file) || ! isset($file['length']) || ! is_numeric($file['length'])) {
                throw new InvalidArgumentException('Torrent file entry is missing length.');
            }

            if (! self::isPadding($file)) {
                $count++;
                $size += (int) $file['length'];
            }
        }

        return ['count' => $count, 'size' => $size];
    }

    /**
     * @param  array<string, mixed>  $info
     * @return list<array{path: string, size: int}>
     */
    public static function listing(array $info): array
    {
        $files = $info['files'] ?? [['path' => [$info['name.utf-8'] ?? $info['name'] ?? null], 'length' => $info['length'] ?? null]];
        $listing = [];

        foreach (is_array($files) ? $files : [] as $file) {
            if (! is_array($file) || self::isPadding($file) || ! is_int($file['length'] ?? null)) {
                continue;
            }

            $path = $file['path.utf-8'] ?? $file['path'] ?? null;
            if (! is_array($path) || $path === [] || count(array_filter($path, 'is_string')) !== count($path)) {
                continue;
            }

            $listing[] = ['path' => UploadTextEncoding::toUtf8(implode('/', $path)), 'size' => $file['length']];
        }

        return $listing;
    }

    /** @param  array<array-key, mixed>  $file */
    private static function isPadding(array $file): bool
    {
        $attributes = $file['attr'] ?? '';
        $path = $file['path'] ?? [];

        return (is_string($attributes) && str_contains($attributes, 'p'))
            || (is_array($path) && count($path) === 2 && ($path[0] ?? null) === '.pad'
                && is_string($path[1] ?? null) && ctype_digit($path[1]));
    }
}
