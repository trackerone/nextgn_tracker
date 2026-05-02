<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;

final class DuplicateTorrentResolver
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveFromContext(array $context): ?Torrent
    {
        $existingTorrentId = $context['existing_torrent_id'] ?? null;

        if (is_int($existingTorrentId)) {
            $torrent = Torrent::query()->find($existingTorrentId);
            if ($torrent instanceof Torrent) {
                return $torrent;
            }
        }

        $infoHash = $context['info_hash'] ?? null;

        if (is_string($infoHash) && $infoHash !== '') {
            $torrent = Torrent::query()->where('info_hash', $infoHash)->first();
            if ($torrent instanceof Torrent) {
                return $torrent;
            }
        }

        return null;
    }
}
