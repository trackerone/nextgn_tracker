<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Torrent;
use App\Models\User;

class TorrentPolicy
{
    public function view(User $user, Torrent $torrent): bool
    {
        if ($torrent->isApproved()) {
            return true;
        }

        return $this->canAccessModeration($user) || $torrent->user_id === $user->id;
    }

    public function viewModerationListings(User $user): bool
    {
        return $this->canAccessModeration($user);
    }

    public function viewModerationItem(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function publish(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function reject(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
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

        return $this->canAccessModeration($user);
    }

    public function create(User $user): bool
    {
        return ! $user->isBanned() && ! $user->isDisabled();
    }

    public function update(User $user, Torrent $torrent): bool
    {
        if ($this->canAccessModeration($user)) {
            return true;
        }

        return $torrent->user_id === $user->id && ! $torrent->isSoftDeleted();
    }

    public function delete(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function moderate(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    private function canAccessModeration(User $user): bool
    {
        return $user->isStaff();
    }
}
