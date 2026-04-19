<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\TorrentFollow;
use App\Models\User;

final class TorrentFollowNavigationBadge
{
    public function __construct(private readonly TorrentFollowInbox $inbox) {}

    public function unseenCountFor(User $user): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, TorrentFollow> $follows */
        $follows = $user->torrentFollows()->get();

        if ($follows->isEmpty()) {
            return 0;
        }

        $inboxItems = $this->inbox->build($follows);

        return $this->inbox->totalNewCount($inboxItems);
    }
}
