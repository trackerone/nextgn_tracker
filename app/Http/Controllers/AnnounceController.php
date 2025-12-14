<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\UserTorrentService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AnnounceController extends Controller
{
    private const MIN_RATIO_FOR_NEW_DOWNLOAD = 0.2;

    public function __construct(
        private readonly BencodeService $bencode,
        private readonly TorrentRepositoryInterface $torrents,
        private readonly UserTorrentService $userTorrents,
    ) {}

    public function __invoke(Request $request, string $passkey): Response
    {
        $user = User::query()->where('passkey', $passkey)->first();

        if ($user === null) {
            return $this->failure('Invalid passkey.');
        }

        $validator = Validator::make($request->query(), [
            'info_hash' => 'required|string',
            'peer_id' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'uploaded' => 'required|integer|min:0',
            'downloaded' => 'required|integer|min:0',
            'left' => 'required|integer|min:0',
            'event' => 'sometimes|string|in:started,stopped,completed',
            'numwant' => 'sometimes|integer|min:1|max:200',
            'ip' => 'sometimes|ip',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->errors()->first());
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();

        // info_hash is typically url-encoded 20 raw bytes (some clients/tests may send 40-char hex)
        $infoHashValue = (string) $data['info_hash'];
        $infoHash = $this->decode20ByteParam($infoHashValue, 'info_hash');

        if ($infoHash instanceof Response) {
            return $infoHash;
        }

        $torrent = $this->torrents->findByInfoHash(strtoupper(bin2hex($infoHash)));

        if ($torrent === null) {
            return $this->failure('Invalid info_hash.');
        }

        // Staff detection: prefer the model method, but allow legacy role strings while migrating
        $isStaff = $user->isStaff() || in_array((string) ($user->role ?? ''), ['moderator', 'admin'], true);

        if ($torrent->isBanned() && ! $isStaff) {
            return $this->failure('Torrent is banned.');
        }

        if (! $torrent->isApproved() && ! $isStaff) {
            return $this->failure('Torrent is not approved yet.');
        }

        // peer_id is also url-encoded 20 raw bytes (some clients/tests may send 40-char hex)
        $peerIdValue = (string) $data['peer_id'];
        $peerId = $this->decode20ByteParam($peerIdValue, 'peer_id');

        if ($peerId instanceof Response) {
            return $peerId;
        }

        $event = isset($data['event']) ? (string) $data['event'] : null;
        $left = (int) $data['left'];
        $isSeeder = $left === 0;
        $ip = (string) ($data['ip'] ?? $request->ip() ?? '0.0.0.0');
        $now = now();

        if (! $isStaff && $event === 'started' && $left > 0) {
            $ratio = $user->ratio();

            if ($ratio !== null && $ratio < self::MIN_RATIO_FOR_NEW_DOWNLOAD) {
                return $this->failure('Your ratio is too low to start new downloads.');
            }
        }

        if ($event === 'stopped') {
            Peer::query()
                ->where('torrent_id', $torrent->id)
                ->where('peer_id', $peerId)
                ->delete();
        } else {
            Peer::query()->updateOrCreate(
                [
                    'torrent_id' => $torrent->id,
                    'peer_id' => $peerId,
                ],
                [
                    'user_id' => $user->getKey(),
                    'ip' => $ip,
                    'port' => (int) $data['port'],
                    'uploaded' => (int) $data['uploaded'],
                    'downloaded' => (int) $data['downloaded'],
                    'left' => $left,
                    'is_seeder' => $isSeeder,
                    'last_announce_at' => $now,
                ],
            );
        }

        if ($event === 'completed') {
            $torrent->increment('completed');
        }

        $this->userTorrents->updateFromAnnounce(
            $user,
            $torrent,
            (int) $data['uploaded'],
            (int) $data['downloaded'],
            $event,
            $now,
        );

        $this->torrents->refreshPeerStats($torrent);

        $numwant = isset($data['numwant']) ? (int) $data['numwant'] : 50;
        $numwant = max(1, min($numwant, 200));
        $activeSince = now()->subMinutes(60);

        $payload = [
            'complete' => $torrent->seeders,
            'incomplete' => $torrent->leechers,
            'interval' => 1800,
            'peers' => $this->peersForResponse($torrent->id, $peerId, $numwant, $activeSince),
        ];

        return $this->success($payload);
    }

    /**
     * Announce parameters like info_hash / peer_id are usually url-encoded raw bytes (20 bytes).
     * Some clients/tests may send 40-hex; we accept that too.
     *
     * @return string|Response
     */
    private function decode20ByteParam(string $value, string $field): string|Response
    {
        $decoded = rawurldecode($value);

        // Allow 40-char hex input (some clients/tests do this)
        if (preg_match('/^[a-f0-9]{40}$/i', $decoded) === 1) {
            $decoded = hex2bin($decoded) ?: '';
        }

        if (strlen($decoded) !== 20) {
            return $this->failure(sprintf('%s must be exactly 20 bytes.', $field));
        }

        return $decoded;
    }

    private function peersForResponse(int $torrentId, string $excludingPeer, int $limit, CarbonInterface $activeSince): array
    {
        return Peer::query()
            ->where('torrent_id', $torrentId)
            ->where('peer_id', '!=', $excludingPeer)
            ->where('last_announce_at', '>=', $activeSince)
            ->orderByDesc('last_announce_at')
            ->limit($limit)
            ->get(['ip', 'port'])
            ->map(static fn (Peer $peer): array => [
                'ip' => $peer->ip,
                'port' => (int) $peer->port,
            ])->all();
    }

    private function success(array $payload): Response
    {
        return $this->bencodedResponse($payload);
    }

    private function failure(string $reason): Response
    {
        return $this->bencodedResponse(['failure reason' => $reason]);
    }

    private function bencodedResponse(array $payload): Response
    {
        return response(
            $this->bencode->encode($payload),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
