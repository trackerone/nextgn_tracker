<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Tracker\UserRatioStatsRecorder;
use App\Services\UserTorrentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PeerEventProcessor
{
    private const MIN_RATIO_FOR_NEW_DOWNLOAD = 0.2;

    public function __construct(
        private readonly UserTorrentService $userTorrents,
        private readonly TorrentRepositoryInterface $torrents,
        private readonly AnnounceSecurityLogger $securityLogger,
        private readonly AnnounceResponseBuilder $responseBuilder,
        private readonly TwentyByteParamDecoder $decoder,
        private readonly UserRatioStatsRecorder $ratioStatsRecorder,
    ) {}

    public function process(Request $request, User $user, Torrent $torrent, AnnounceRequestData $data): AnnounceResult
    {
        $peerId = $this->decoder->decode($data->peerId);
        if ($peerId === null) {
            return AnnounceResult::failure('peer_id must be exactly 20 bytes.');
        }

        $oldPeer = Peer::query()
            ->where('torrent_id', $torrent->getKey())
            ->where('peer_id', $peerId)
            ->first();

        if ($data->event === null && $this->shouldShortCircuitDuplicateAnnounce($request, $data, $oldPeer)) {
            $this->securityLogger->log(
                request: $request,
                user: $user,
                eventType: 'tracker.rate_limited',
                severity: 'medium',
                message: 'Rate limit violation during announce',
                context: ['torrent_id' => $torrent->getKey()],
            );

            return $this->responseBuilder->successWithoutPeers($torrent);
        }

        if ($data->event === 'started' && $data->left > 0 && ! $this->isStaff($user)) {
            $ratio = $user->ratio();

            if ($ratio !== null && $ratio < self::MIN_RATIO_FOR_NEW_DOWNLOAD) {
                return AnnounceResult::failure('Your ratio is too low to start new downloads.');
            }
        }

        $this->persistPeerState($request, $user, $torrent, $data, $peerId, $oldPeer);
        $this->persistUserTorrentState($user, $torrent, $data);
        $this->ratioStatsRecorder->record($user, $torrent, $oldPeer, $data);

        $this->torrents->refreshPeerStats($torrent);

        return $this->responseBuilder->successWithPeers($torrent, $peerId, $data->numwant);
    }

    private function persistPeerState(Request $request, User $user, Torrent $torrent, AnnounceRequestData $data, string $peerId, ?Peer $existingPeer): void
    {
        $now = now();
        $ip = (string) ($data->ip ?? $request->ip() ?? '0.0.0.0');

        if ($data->event === 'stopped') {
            Peer::query()
                ->where('torrent_id', $torrent->getKey())
                ->where('peer_id', $peerId)
                ->delete();

            return;
        }

        $existingUploaded = $existingPeer instanceof Peer ? (int) $existingPeer->uploaded : 0;
        $existingDownloaded = $existingPeer instanceof Peer ? (int) $existingPeer->downloaded : 0;

        Peer::query()->updateOrCreate(
            [
                'torrent_id' => $torrent->getKey(),
                'peer_id' => $peerId,
            ],
            [
                'user_id' => $user->getKey(),
                'ip' => $ip,
                'port' => $data->port,
                'uploaded' => max($existingUploaded, $data->uploaded),
                'downloaded' => max($existingDownloaded, $data->downloaded),
                'left' => $data->left,
                'is_seeder' => $data->left === 0,
                'last_announce_at' => $now,
            ],
        );

        if ($data->event === 'completed' && $this->shouldIncrementCompletedCounter($user, $torrent)) {
            $torrent->increment('completed');
        }
    }

    private function persistUserTorrentState(User $user, Torrent $torrent, AnnounceRequestData $data): void
    {
        $now = now();

        $this->userTorrents->updateFromAnnounce(
            $user,
            $torrent,
            $data->uploaded,
            $data->downloaded,
            $data->event,
            $now,
        );
    }

    private function shouldShortCircuitDuplicateAnnounce(Request $request, AnnounceRequestData $data, ?Peer $peer): bool
    {
        if ($peer === null) {
            return false;
        }

        $ip = (string) ($data->ip ?? $request->ip() ?? '0.0.0.0');

        return (int) $peer->port === $data->port
            && (int) $peer->uploaded === $data->uploaded
            && (int) $peer->downloaded === $data->downloaded
            && (int) $peer->left === $data->left
            && (string) $peer->ip === $ip;
    }

    private function shouldIncrementCompletedCounter(User $user, Torrent $torrent): bool
    {
        $completedAt = DB::table('user_torrents')
            ->where('user_id', $user->getKey())
            ->where('torrent_id', $torrent->getKey())
            ->value('completed_at');

        return $completedAt === null;
    }

    private function isStaff(User $user): bool
    {
        return $user->isStaff()
            || in_array((string) ($user->role ?? ''), [
                'moderator',
                'admin',
                'sysop',
                'mod1',
                'mod2',
                'admin1',
                'admin2',
            ], true);
    }
}
