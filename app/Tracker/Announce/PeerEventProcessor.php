<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Tracker\AnnounceIntegrityEvaluation;
use App\Services\Tracker\AnnounceIntegrityEvaluator;
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
        private readonly AnnounceIntegrityEvaluator $integrityEvaluator,
    ) {}

    public function process(Request $request, User $user, Torrent $torrent, AnnounceRequestData $data): AnnounceResult
    {
        $peerId = $this->decoder->decode($data->peerId);
        if ($peerId === null) {
            return AnnounceResult::failure('peer_id must be exactly 20 bytes.');
        }

        if ($data->event === 'started' && $data->left > 0 && ! $this->isStaff($user)) {
            $ratio = $user->ratio();

            if ($ratio !== null && $ratio < self::MIN_RATIO_FOR_NEW_DOWNLOAD) {
                return AnnounceResult::failure('Your ratio is too low to start new downloads.');
            }
        }

        $result = DB::transaction(function () use ($request, $user, $torrent, $data, $peerId): array {
            $oldPeer = Peer::query()
                ->where('torrent_id', $torrent->getKey())
                ->where('user_id', $user->getKey())
                ->where('peer_id', $peerId)
                ->lockForUpdate()
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

                return ['short_circuit' => true];
            }

            $integrity = $this->integrityEvaluator->evaluate($oldPeer, $data);

            $this->persistPeerState($request, $user, $torrent, $data, $peerId, $oldPeer);

            if ($this->persistUserTorrentState($user, $torrent, $data)) {
                $torrent->increment('completed');
            }

            $this->ratioStatsRecorder->record($user, $torrent, $integrity);

            return [
                'short_circuit' => false,
                'integrity' => $integrity,
                'old_peer' => $oldPeer,
            ];
        });

        if (($result['short_circuit'] ?? false) === true) {
            return $this->responseBuilder->successWithoutPeers($torrent);
        }

        /** @var AnnounceIntegrityEvaluation $integrity */
        $integrity = $result['integrity'];
        /** @var Peer|null $oldPeer */
        $oldPeer = $result['old_peer'];

        $this->logIntegrityEvents($request, $user, $torrent, $peerId, $oldPeer, $data, $integrity);
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
                ->where('user_id', $user->getKey())
                ->where('peer_id', $peerId)
                ->delete();

            return;
        }

        $existingUploaded = $existingPeer instanceof Peer ? (int) $existingPeer->uploaded : 0;
        $existingDownloaded = $existingPeer instanceof Peer ? (int) $existingPeer->downloaded : 0;

        $peer = $existingPeer ?? new Peer([
            'torrent_id' => $torrent->getKey(),
            'user_id' => $user->getKey(),
            'peer_id' => $peerId,
        ]);

        $peer->user_id = $user->getKey();
        $peer->ip = $ip;
        $peer->port = $data->port;
        $peer->uploaded = max($existingUploaded, $data->uploaded);
        $peer->downloaded = max($existingDownloaded, $data->downloaded);
        $peer->left = $data->left;
        $peer->is_seeder = $data->left === 0;
        $peer->last_announce_at = $now;
        $peer->save();
    }

    private function persistUserTorrentState(User $user, Torrent $torrent, AnnounceRequestData $data): bool
    {
        $now = now();

        $userTorrent = $this->userTorrents->updateFromAnnounce(
            $user,
            $torrent,
            $data->uploaded,
            $data->downloaded,
            $data->event,
            $now,
        );

        return $data->event === 'completed' && $userTorrent->wasChanged('completed_at');
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

    private function logIntegrityEvents(
        Request $request,
        User $user,
        Torrent $torrent,
        string $peerId,
        ?Peer $oldPeer,
        AnnounceRequestData $newState,
        AnnounceIntegrityEvaluation $integrity,
    ): void {
        if (! $integrity->isSuspicious()) {
            return;
        }

        $context = [
            'user_id' => $user->getKey(),
            'torrent_id' => $torrent->getKey(),
            'peer_id' => bin2hex($peerId),
            'old_uploaded' => $oldPeer instanceof Peer ? (int) $oldPeer->uploaded : null,
            'new_uploaded' => $newState->uploaded,
            'old_downloaded' => $oldPeer instanceof Peer ? (int) $oldPeer->downloaded : null,
            'new_downloaded' => $newState->downloaded,
            'old_left' => $oldPeer instanceof Peer ? (int) $oldPeer->left : null,
            'new_left' => $newState->left,
            'reasons' => $integrity->reasons,
        ];

        $this->securityLogger->log(
            request: $request,
            user: $user,
            eventType: 'tracker.announce.suspicious_delta',
            severity: 'medium',
            message: 'Suspicious announce delta detected.',
            context: $context,
        );

        if ($integrity->hasReason('completion_rollback')) {
            $this->securityLogger->log(
                request: $request,
                user: $user,
                eventType: 'tracker.announce.completion_rollback',
                severity: 'medium',
                message: 'Completion rollback detected during announce.',
                context: $context,
            );
        }
    }
}
