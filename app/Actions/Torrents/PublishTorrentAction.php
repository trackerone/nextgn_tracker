<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Enums\TorrentStatus;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;

final class PublishTorrentAction
{
    public function execute(Torrent $torrent, User $moderator): Torrent
    {
        if (! $torrent->status->isModeratable()) {
            throw InvalidTorrentStatusTransitionException::fromStatus(
                $torrent->status->value,
                TorrentStatus::Published->value,
            );
        }

        $now = Carbon::now();

        $torrent->forceFill([
            'status' => TorrentStatus::Published,
            'is_approved' => true,
            'published_at' => $torrent->published_at ?? $now,
            'moderated_by' => $moderator->id,
            'moderated_at' => $now,
        ])->save();

        return $torrent;
    }
}
