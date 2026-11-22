<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Jobs\MatchTorrentWatchPresets;
use App\Models\Torrent;
use App\Models\TorrentMetadata;

final class PersistTorrentMetadataService
{
    /** @var list<string> */
    private const WATCH_FILTER_FIELDS = [
        'title',
        'year',
        'type',
        'resolution',
        'source',
        'release_group',
        'language',
        'audio_language',
        'subtitle_language',
        'subtitles',
    ];

    public function persist(Torrent $torrent, CanonicalTorrentMetadata $metadata): TorrentMetadata
    {
        $saved = TorrentMetadata::query()->updateOrCreate(
            ['torrent_id' => $torrent->getKey()],
            $metadata->toPersistenceArray(),
        );

        // Normal uploads are pending here, so the publish action queues their
        // first match. Only already-visible releases need a metadata rematch.
        if ($torrent->isVisible() && ($saved->wasRecentlyCreated || $saved->wasChanged(self::WATCH_FILTER_FIELDS))) {
            MatchTorrentWatchPresets::dispatch((int) $torrent->getKey())->afterCommit();
        }

        return $saved;
    }
}
