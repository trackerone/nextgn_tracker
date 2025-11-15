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

        $userTorrent->fill([
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'last_announce_at' => $announcedAt,
        ]);

        if ($event === 'completed' && $userTorrent->completed_at === null) {
            $userTorrent->completed_at = $announcedAt;
        }

        $userTorrent->save();

        return $userTorrent;
    }

    public function recordGrab(User $user, Torrent $torrent, CarbonInterface $grabbedAt): UserTorrent
    {
        $userTorrent = UserTorrent::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'torrent_id' => $torrent->getKey(),
        ]);

        if ($userTorrent->first_grab_at === null) {
            $userTorrent->first_grab_at = $grabbedAt;
        }

        $userTorrent->last_grab_at = $grabbedAt;
        $userTorrent->save();

        return $userTorrent;
    }
}
