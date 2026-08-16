<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\Torrent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class GhostPeerPruner
{
    public function __construct(private readonly TorrentRepositoryInterface $torrents) {}

    /**
     * @return array{matched: int, deleted: int, affected_torrents: int}
     */
    public function prune(CarbonInterface $cutoff, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($cutoff, $dryRun): array {
            $stalePeerQuery = Peer::query()
                ->where('last_announce_at', '<=', $cutoff);

            if (! $dryRun) {
                $stalePeerQuery->lockForUpdate();
            }

            /** @var Collection<int, Peer> $stalePeers */
            $stalePeers = $stalePeerQuery->get(['id', 'torrent_id']);

            $peerIds = $stalePeers->modelKeys();
            $torrentIds = $stalePeers
                ->pluck('torrent_id')
                ->map(static fn (mixed $torrentId): int => (int) $torrentId)
                ->unique()
                ->values()
                ->all();

            $result = [
                'matched' => count($peerIds),
                'deleted' => 0,
                'affected_torrents' => count($torrentIds),
            ];

            if ($dryRun || $peerIds === []) {
                return $result;
            }

            $result['deleted'] = Peer::query()
                ->whereKey($peerIds)
                ->where('last_announce_at', '<=', $cutoff)
                ->delete();

            /** @var Collection<int, Torrent> $affectedTorrents */
            $affectedTorrents = Torrent::query()->whereKey($torrentIds)->get();

            foreach ($affectedTorrents as $torrent) {
                $this->torrents->refreshPeerStats($torrent);
            }

            return $result;
        });
    }
}
