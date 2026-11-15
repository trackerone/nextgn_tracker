<?php

declare(strict_types=1);

namespace App\Support\Torrents;

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

            $attributes = $file['attr'] ?? '';
            $path = $file['path'] ?? [];
            $padding = (is_string($attributes) && str_contains($attributes, 'p'))
                || (is_array($path) && count($path) === 2 && ($path[0] ?? null) === '.pad'
                    && is_string($path[1] ?? null) && ctype_digit($path[1]));

            if (! $padding) {
                $count++;
                $size += (int) $file['length'];
            }
        }

        return ['count' => $count, 'size' => $size];
    }
}
