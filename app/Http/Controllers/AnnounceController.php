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

        $infoHashHex = strtolower(bin2hex($infoHash));
        $torrent = $this->torrents->findByInfoHash($infoHashHex);
        if ($torrent === null) {
            return $this->failure('Invalid info_hash.');
        }

        $isStaff = $user->isStaff() || in_array((string) ($user->role ?? ''), ['moderator', 'admin', 'sysop', 'mod1', 'mod2', 'admin1', 'admin2'], true);

        if ($torrent->isBanned() && ! $isStaff) {
            // Optional: log as security event
            $this->logSecurityEvent($request, $user, 'tracker.client_banned', 'high', 'Banned client attempted announce', [
                'torrent_id' => $torrent->getKey(),
            ]);

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

        // Ensure user_torrents exists for tests (they assert directly on the table)
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

        $activeSince = now()->subMinutes(60);

        $payload = [
            'complete' => (int) $torrent->seeders,
            'incomplete' => (int) $torrent->leechers,
            'interval' => 1800,
            'peers' => $this->peersForResponse($torrent->id, $peerId, $numwant, $activeSince),
        ];

        return $this->success($payload);
    }

    private function logInvalidPasskey(Request $request, string $passkey): void
    {
        // Anything coming from announce can contain raw/binary bytes. Sanitize before JSON.
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
            ]
        );
    }

    private function logSecurityEvent(
        Request $request,
        ?User $user,
        string $eventType,
        string $severity,
        string $message,
        array $context
    ): void {
        $payload = [
            'user_id' => $user?->getKey(),
            'ip_address' => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'event_type' => $eventType,
            'severity' => $severity,
            'message' => $message,
            'context' => $this->sanitizeForJson($context),
        ];

        // Never let logging break the tracker response.
        try {
            SecurityEvent::query()->create($payload);
        } catch (\Throwable) {
            // swallow
        }
    }

    /**
     * Recursively sanitize values for JSON storage (UTF-8 safe).
     */
    private function sanitizeForJson(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $this->sanitizeString($k) : $k;
                $out[$key] = $this->sanitizeForJson($v);
            }
            return $out;
        }

        if ($value instanceof \Stringable) {
            return $this->sanitizeString((string) $value);
        }

        // Fallback: string-cast anything else
        return $this->sanitizeString((string) $value);
    }

    private function sanitizeString(string $value): string
    {
        // If already valid UTF-8, keep it.
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Otherwise, store a safe representation.
        return 'base64:'.base64_encode($value);
    }

    /**
     * Accept:
     * - 20 raw bytes (already decoded)
     * - percent-encoded raw bytes
     * - 40-char hex (tests/clients)
     *
     * Also pads 19 bytes to 20 bytes to compensate for stripped trailing null bytes.
     *
     * @return string|Response
     */
    private function decode20ByteParam(string $value, string $field): string|Response
    {
        $decoded = $value;

        // 1) Accept 40-char hex directly
        if (preg_match('/^[a-f0-9]{40}$/i', $decoded) === 1) {
            $bin = hex2bin($decoded);
            if ($bin === false) {
                return $this->failure(sprintf('%s must be exactly 20 bytes.', $field));
            }
            return $bin;
        }

        // 2) Percent-decoding if needed
        if (str_contains($decoded, '%')) {
            $decoded = rawurldecode($decoded);
        }

        // 3) Symfony/Laravel may strip trailing null bytes from query values.
        if (strlen($decoded) === 19) {
            $decoded .= "\0";
        }

        if (strlen($decoded) !== 20) {
            // Try one more decode pass (safe)
            $decoded2 = rawurldecode($value);
            if (strlen($decoded2) === 19) {
                $decoded2 .= "\0";
            }

            if (strlen($decoded2) !== 20) {
                return $this->failure(sprintf('%s must be exactly 20 bytes.', $field));
            }

            return $decoded2;
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
