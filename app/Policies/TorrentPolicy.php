<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Torrent;
use App\Models\User;

class TorrentPolicy
{
    public function view(User $user, Torrent $torrent): bool
    {
        return $torrent->isApproved()
            || $torrent->user_id === $user->id
            || $user->isStaff();
    }

    public function download(User $user, Torrent $torrent): bool
    {
        if ($torrent->isApproved()) {
            return true;
        }

        if ($torrent->user_id === $user->id) {
            // Uploaders may download their own torrents while waiting for moderation.
            return true;
        }

        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return !$user->isBanned() && !$user->isDisabled();
    }

    public function update(User $user, Torrent $torrent): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $torrent->user_id === $user->id && !$torrent->isSoftDeleted();
    }

    public function delete(User $user, Torrent $torrent): bool
    {
        return $user->isStaff();
    }

    public function moderate(User $user, Torrent $torrent): bool
    {
        return $user->isStaff();
    }
}
