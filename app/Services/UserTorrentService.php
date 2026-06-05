<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        UserTorrent::query()->insertOrIgnore([
            'user_id' => $user->getKey(),
            'torrent_id' => $torrent->getKey(),
            'uploaded' => 0,
            'downloaded' => 0,
            'created_at' => $announcedAt,
            'updated_at' => $announcedAt,
        ]);

        $userTorrent = UserTorrent::query()
            ->where('user_id', $user->getKey())
            ->where('torrent_id', $torrent->getKey())
            ->lockForUpdate()
            ->first();

        if (! $userTorrent instanceof UserTorrent) {
            throw new ModelNotFoundException('User torrent row could not be resolved for announce update.');
        }

        $userTorrent->fill([
            'uploaded' => max((int) ($userTorrent->uploaded ?? 0), $uploaded),
            'downloaded' => max((int) ($userTorrent->downloaded ?? 0), $downloaded),
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
