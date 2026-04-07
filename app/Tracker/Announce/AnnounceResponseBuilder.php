<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Models\Peer;
use App\Models\Torrent;
use Carbon\CarbonInterface;

final class AnnounceResponseBuilder
{
    public function successWithPeers(Torrent $torrent, string $excludingPeerId, int $numwant): AnnounceResult
    {
        return AnnounceResult::success([
            'complete' => (int) $torrent->seeders,
            'incomplete' => (int) $torrent->leechers,
            'interval' => 1800,
            'peers' => $this->peersForResponse(
                torrentId: (int) $torrent->getKey(),
                excludingPeer: $excludingPeerId,
                limit: $numwant,
                activeSince: now()->subMinutes(60),
            ),
        ]);
    }

    public function successWithoutPeers(Torrent $torrent): AnnounceResult
    {
        return AnnounceResult::success([
            'complete' => (int) $torrent->seeders,
            'incomplete' => (int) $torrent->leechers,
            'interval' => 1800,
            'peers' => [],
        ]);
    }

    private function peersForResponse(int $torrentId, string $excludingPeer, int $limit, CarbonInterface $activeSince): array
    {
        return Peer::query()
            ->where('torrent_id', $torrentId)
            ->where('peer_id', '!=', $excludingPeer)
            ->where('last_announce_at', '>=', $activeSince)
            ->orderByDesc('last_announce_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['ip', 'port'])
            ->map(static fn (Peer $peer): array => [
                'ip' => $peer->ip,
                'port' => (int) $peer->port,
            ])
            ->all();
    }
}
