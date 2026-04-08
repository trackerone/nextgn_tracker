<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;

final class DownloadEligibilityService
{
    public function canDownload(User $user, Torrent $torrent): bool
    {
        if ($torrent->isApproved()) {
            return true;
        }

        if ((int) $torrent->user_id === (int) $user->getKey()) {
            return true;
        }

        return $user->isStaff();
    }
}
