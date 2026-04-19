<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;

final class TorrentFollowNavigationBadge
{
    public function __construct(private readonly TorrentFollowInbox $inbox) {}

    public function unseenCountFor(User $user): int
    {
        $follows = $user->torrentFollows()->get();

        if ($follows->isEmpty()) {
            return 0;
        }

        $inboxItems = $this->inbox->build($follows);

        return $this->inbox->totalNewCount($inboxItems);
    }
}
