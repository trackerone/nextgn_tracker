<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Peer;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\UserTorrentService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AnnounceController extends Controller
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
            $this->logInvalidPasskey($request, $passkey);

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

        $infoHash = $this->decode20ByteParam((string) $data['info_hash'], 'info_hash');
        if ($infoHash instanceof Response) {
            return $infoHash;
        }

        // Info_hash is case-insensitive as hex. Try both to avoid DB normalization issues.
        $infoHashHex = bin2hex($infoHash);

        $torrent = $this->torrents->findByInfoHash(strtolower($infoHashHex));
        if ($torrent === null) {
            $torrent = $this->torrents->findByInfoHash(strtoupper($infoHashHex));
        }

        if ($torrent === null) {
            return $this->failure('Invalid info_hash.');
        }

        $isStaff = $user->isStaff()
            || in_array((string) ($user->role ?? ''), [
                'moderator',
                'admin',
                'sysop',
                'mod1',
                'mod2',
                'admin1',
                'admin2',
            ], true);

        if ($torrent->isBanned() && ! $isStaff) {
            $this->logSecurityEvent(
                $request,
                $user,
                'tracker.client_banned',
                'high',
                'Banned client attempted announce',
                ['torrent_id' => $torrent->getKey()],
            );

            return $this->failure('Torrent is banned.');
        }

        if (! $torrent->isApproved() && ! $isStaff) {
            return $this->failure('Torrent is not approved yet.');
        }

        $peerId = $this->decode20ByteParam((string) $data['peer_id'], 'peer_id');
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

        DB::table('user_torrents')->updateOrInsert(
            [
                'user_id' => $user->getKey(),
                'torrent_id' => $torrent->id,
            ],
            [
                'uploaded' => (int) $data['uploaded'],
                'downloaded' => (int) $data['downloaded'],
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

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

        $payload = [
            'complete' => (int) $torrent->seeders,
            'incomplete' => (int) $torrent->leechers,
            'interval' => 1800,
            'peers' => $this->peersForResponse(
                $torrent->id,
                $peerId,
                $numwant,
                now()->subMinutes(60),
            ),
        ];

        return $this->success($payload);
    }

    /**
     * Deterministic 20-byte decoder for tracker params.
     * Fixes flakiness caused by stripped trailing NULL bytes.
     */
    private function decode20ByteParam(string $value, string $field): string|Response
    {
        $raw = $value;

        // 1) 40-char hex
        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $raw) === 1) {
            $bin = hex2bin($raw);
            if ($bin === false) {
                return $this->failure(sprintf('%s must be exactly 20 bytes.', $field));
            }

            return $bin;
        }

        // 2) Raw bytes (already decoded)
        $len = strlen($raw);
        if ($len === 20) {
            return $raw;
        }

        if ($len === 19) {
            return str_pad($raw, 20, "\0", STR_PAD_RIGHT);
        }

        // 3) Percent-encoded bytes
        if (preg_match('/%[0-9A-Fa-f]{2}/', $raw) === 1) {
            $decoded = rawurldecode($raw);
            $dlen = strlen($decoded);

            if ($dlen === 20) {
                return $decoded;
            }

            if ($dlen === 19) {
                return str_pad($decoded, 20, "\0", STR_PAD_RIGHT);
            }

            if (preg_match('/\A[0-9a-fA-F]{40}\z/', $decoded) === 1) {
                $bin = hex2bin($decoded);
                if ($bin !== false) {
                    return $bin;
                }
            }
        }

        return $this->failure(sprintf('%s must be exactly 20 bytes.', $field));
    }

    private function peersForResponse(
        int $torrentId,
        string $excludingPeer,
        int $limit,
        CarbonInterface $activeSince
    ): array {
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
            ])
            ->all();
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
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }

    private function logInvalidPasskey(Request $request, string $passkey): void
    {
        $this->logSecurityEvent(
            $request,
            null,
            'tracker.invalid_passkey',
            'low',
            'Invalid passkey used during announce attempt',
            [
                'passkey' => $passkey,
                'path' => $request->path(),
                'query' => $request->query(),
                'headers' => [
                    'user-agent' => $request->userAgent(),
                ],
            ],
        );
    }

    private function logSecurityEvent(
        Request $request,
        ?User $user,
        string $eventType,
        string $severity,
        string $message,
        array $context,
    ): void {
        try {
            SecurityEvent::query()->create([
                'user_id' => $user?->getKey(),
                'ip_address' => (string) ($request->ip() ?? '0.0.0.0'),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'event_type' => $eventType,
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Throwable) {
            // never break announce
        }
    }
}
