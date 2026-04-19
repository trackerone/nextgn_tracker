<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentFollow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TorrentFollowInbox
{
    public function __construct(private readonly TorrentFollowMatcher $matcher) {}

    /**
     * @param  Collection<int, TorrentFollow>  $follows
     * @return array<int, array{follow: TorrentFollow, allMatches: Collection<int, Torrent>, newMatches: Collection<int, Torrent>, newCount: int}>
     */
    public function build(Collection $follows): array
    {
        $matchesByFollowId = $this->matcher->matchesForFollows($follows);

        return $follows->map(function (TorrentFollow $follow) use ($matchesByFollowId): array {
            /** @var Collection<int, Torrent> $allMatches */
            $allMatches = $matchesByFollowId[$follow->getKey()] ?? collect();

            $newMatches = $allMatches
                ->filter(fn (Torrent $torrent): bool => $this->isNewMatch($follow, $torrent))
                ->values();

            return [
                'follow' => $follow,
                'allMatches' => $allMatches,
                'newMatches' => $newMatches,
                'newCount' => $newMatches->count(),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, TorrentFollow>  $follows
     */
    public function markAsSeen(Collection $follows, CarbonImmutable $checkedAt): void
    {
        if ($follows->isEmpty()) {
            return;
        }

        TorrentFollow::query()
            ->whereIn('id', $follows->pluck('id'))
            ->update([
                'last_checked_at' => $checkedAt,
                'updated_at' => $checkedAt,
            ]);
    }

    public function totalNewCount(array $inboxItems): int
    {
        return (int) collect($inboxItems)
            ->sum(fn (array $item): int => (int) ($item['newCount'] ?? 0));
    }

    private function isNewMatch(TorrentFollow $follow, Torrent $torrent): bool
    {
        if ($follow->last_checked_at === null) {
            return true;
        }

        return $torrent->created_at !== null
            && $torrent->created_at->greaterThan($follow->last_checked_at);
    }
}
