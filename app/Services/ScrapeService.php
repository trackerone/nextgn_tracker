<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Torrent;

class ScrapeService
{
    /**
     * @param  array<int, string>  $infoHashes  Uppercase hex-encoded info_hash values.
     * @return array{files: array<string, array{complete: int, incomplete: int, downloaded: int}>}
     */
    public function buildResponse(array $infoHashes): array
    {
        $uniqueHashes = array_values(array_unique($infoHashes));

        if ($uniqueHashes === []) {
            return ['files' => []];
        }

        $torrents = Torrent::query()
            ->whereIn('info_hash', $uniqueHashes)
            ->get(['info_hash', 'seeders', 'leechers', 'completed'])
            ->keyBy('info_hash');

        $files = [];

        foreach ($uniqueHashes as $hash) {
            /** @var Torrent|null $torrent */
            $torrent = $torrents->get($hash);

            $files[$hash] = [
                'complete' => $torrent !== null ? (int) $torrent->seeders : 0,
                'incomplete' => $torrent !== null ? (int) $torrent->leechers : 0,
                'downloaded' => $torrent !== null ? (int) $torrent->completed : 0,
            ];
        }

        return ['files' => $files];
    }
}
