<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Torrent;
use App\Models\TorrentUserStat;
use App\Models\User;
use App\Models\UserStat;

final class UserRatioStatsRecorder
{
    public function __construct(private readonly RatioCreditCalculator $ratioCreditCalculator) {}

    public function record(User $user, Torrent $torrent, AnnounceIntegrityEvaluation $integrity): void
    {
        $credited = $this->ratioCreditCalculator->calculate(
            $integrity->uploadedDelta,
            $integrity->downloadedDelta,
            (bool) $torrent->is_freeleech,
        );

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

        $torrentUserStat->uploaded_bytes += $credited['uploaded'];
        $torrentUserStat->downloaded_bytes += $credited['downloaded'];
        $torrentUserStat->last_announced_at = $now;

        if ($integrity->isCompletionTransition) {
            $isFirstUniqueCompletion = $torrentUserStat->times_completed === 0;

            $torrentUserStat->times_completed += 1;
            $torrentUserStat->first_completed_at ??= $now;
            $torrentUserStat->last_completed_at = $now;

            if ($isFirstUniqueCompletion) {
                $userStat->completed_torrents_count += 1;
            }
        }

        $torrentUserStat->save();

        $userStat->uploaded_bytes += $credited['uploaded'];
        $userStat->downloaded_bytes += $credited['downloaded'];
        $userStat->last_announced_at = $now;
        $userStat->save();
    }
}
