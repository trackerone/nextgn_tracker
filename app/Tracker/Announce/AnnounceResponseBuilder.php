<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Models\Peer;
use App\Models\Torrent;
use App\Services\Settings\TrackerRuntimeSettings;
use DateTimeInterface;

final class AnnounceResponseBuilder
{
    public function __construct(private readonly TrackerRuntimeSettings $settings) {}

    public function successWithPeers(Torrent $torrent, string $excludingPeerId, int $numwant): AnnounceResult
    {
        $activeSince = now()->subMinutes(max(1, $this->settings->ghostTimeout()));

        return AnnounceResult::success([
            'complete' => (int) $torrent->seeders,
            'incomplete' => (int) $torrent->leechers,
            'interval' => $this->settings->announceInterval(),
            'peers' => $this->peersForResponse(
                torrentId: (int) $torrent->getKey(),
                excludingPeer: $excludingPeerId,
                limit: $numwant,
                activeSince: $activeSince,
            ),
        ]);
    }

    public function successWithoutPeers(Torrent $torrent): AnnounceResult
    {
        $activeSince = now()->subMinutes(max(1, $this->settings->ghostTimeout()));

        return AnnounceResult::success([
            'complete' => $this->activePeerCount((int) $torrent->getKey(), true, $activeSince),
            'incomplete' => $this->activePeerCount((int) $torrent->getKey(), false, $activeSince),
            'interval' => $this->settings->announceInterval(),
            'peers' => [],
        ]);
    }

    private function peersForResponse(int $torrentId, string $excludingPeer, int $limit, DateTimeInterface $activeSince): array
    {
        return Peer::query()
            ->where('torrent_id', $torrentId)
            ->where('peer_id', '!=', $excludingPeer)
            ->where('last_announce_at', '>', $activeSince)
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

    private function activePeerCount(int $torrentId, bool $isSeeder, DateTimeInterface $activeSince): int
    {
        return Peer::query()
            ->where('torrent_id', $torrentId)
            ->where('is_seeder', $isSeeder)
            ->where('last_announce_at', '>', $activeSince)
            ->count();
    }
}
