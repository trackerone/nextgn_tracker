<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Peer;
use App\Models\Torrent;
use App\Models\TorrentUserStat;
use App\Models\User;
use App\Models\UserStat;
use App\Tracker\Announce\AnnounceRequestData;
use Illuminate\Support\Facades\DB;

final class UserRatioStatsRecorder
{
    public function record(User $user, Torrent $torrent, ?Peer $oldPeer, AnnounceRequestData $newState): void
    {
        $uploadedDelta = 0;
        $downloadedDelta = 0;

        if ($oldPeer instanceof Peer) {
            $uploadedDelta = max(0, $newState->uploaded - (int) $oldPeer->uploaded);
            $downloadedDelta = max(0, $newState->downloaded - (int) $oldPeer->downloaded);
        }

        $isCompletionTransition = $oldPeer instanceof Peer
            && (int) $oldPeer->left > 0
            && $newState->left === 0;

        DB::transaction(function () use ($user, $torrent, $uploadedDelta, $downloadedDelta, $isCompletionTransition): void {
            $now = now();

            $userStat = UserStat::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->getKey()],
                    [
                        'uploaded_bytes' => 0,
                        'downloaded_bytes' => 0,
                        'seed_time_seconds' => 0,
                        'leech_time_seconds' => 0,
                        'completed_torrents_count' => 0,
                    ],
                );

            $torrentUserStat = TorrentUserStat::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'torrent_id' => $torrent->getKey(),
                    ],
                    [
                        'uploaded_bytes' => 0,
                        'downloaded_bytes' => 0,
                        'seed_time_seconds' => 0,
                        'leech_time_seconds' => 0,
                        'times_completed' => 0,
                    ],
                );

            $torrentUserStat->uploaded_bytes += $uploadedDelta;
            $torrentUserStat->downloaded_bytes += $downloadedDelta;
            $torrentUserStat->last_announced_at = $now;

            if ($isCompletionTransition) {
                $isFirstUniqueCompletion = $torrentUserStat->times_completed === 0;

                $torrentUserStat->times_completed += 1;
                $torrentUserStat->first_completed_at ??= $now;
                $torrentUserStat->last_completed_at = $now;

                if ($isFirstUniqueCompletion) {
                    $userStat->completed_torrents_count += 1;
                }
            }

            $torrentUserStat->save();

            $userStat->uploaded_bytes += $uploadedDelta;
            $userStat->downloaded_bytes += $downloadedDelta;
            $userStat->last_announced_at = $now;
            $userStat->save();
        });
    }
}
