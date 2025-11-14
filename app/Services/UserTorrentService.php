<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Carbon\CarbonInterface;

class UserTorrentService
{
    public function updateFromAnnounce(
        User $user,
        Torrent $torrent,
        int $uploaded,
        int $downloaded,
        ?string $event,
        CarbonInterface $announcedAt,
    ): UserTorrent {
        $userTorrent = UserTorrent::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'torrent_id' => $torrent->getKey(),
        ]);

        $currentUploaded = (int) $userTorrent->uploaded;
        $currentDownloaded = (int) $userTorrent->downloaded;

        $userTorrent->fill([
            'uploaded' => max($currentUploaded, $uploaded),
            'downloaded' => max($currentDownloaded, $downloaded),
            'last_announce_at' => $announcedAt,
        ]);

        if ($event === 'completed' && $userTorrent->completed_at === null) {
            $userTorrent->completed_at = $announcedAt;
        }

        $userTorrent->save();

        return $userTorrent;
    }
}
