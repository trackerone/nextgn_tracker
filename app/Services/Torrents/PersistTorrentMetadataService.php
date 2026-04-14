<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentMetadata;

final class PersistTorrentMetadataService
{
    public function persist(Torrent $torrent, CanonicalTorrentMetadata $metadata): TorrentMetadata
    {
        return TorrentMetadata::query()->updateOrCreate(
            ['torrent_id' => $torrent->getKey()],
            $metadata->toPersistenceArray(),
        );
    }
}
