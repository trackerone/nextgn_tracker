<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Enums\TorrentStatus;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;

final class RejectTorrentAction
{
    public function execute(Torrent $torrent, User $moderator, ?string $reason = null): Torrent
    {
        if (! $torrent->status->isModeratable()) {
            throw InvalidTorrentStatusTransitionException::fromStatus(
                $torrent->status->value,
                TorrentStatus::Rejected->value,
            );
        }

        $torrent->forceFill([
            'status' => TorrentStatus::Rejected,
            'is_approved' => false,
            'published_at' => null,
            'moderated_by' => $moderator->id,
            'moderated_at' => Carbon::now(),
            'moderated_reason' => $reason ?? $torrent->moderated_reason,
        ])->save();

        return $torrent;
    }
}
