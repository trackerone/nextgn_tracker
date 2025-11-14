<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Peer;
use App\Models\Torrent;
use App\Services\BencodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AnnounceController extends Controller
{
    public function __construct(private readonly BencodeService $bencode)
    {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->failure('Authentication required.');
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
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->errors()->first());
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();

        $infoHash = (string) $data['info_hash'];

        if (strlen($infoHash) !== 20) {
            return $this->failure('info_hash must be exactly 20 bytes.');
        }

        $torrent = Torrent::query()
            ->where('info_hash', Str::upper(bin2hex($infoHash)))
            ->first();

        if ($torrent === null) {
            return $this->failure('Invalid info_hash.');
        }

        $peerId = (string) $data['peer_id'];

        if (strlen($peerId) !== 20) {
            return $this->failure('peer_id must be exactly 20 bytes.');
        }

        $event = isset($data['event']) ? (string) $data['event'] : null;
        $left = (int) $data['left'];
        $isSeeder = $left === 0;
        $ip = (string) ($request->query('ip') ?? $request->ip() ?? '0.0.0.0');
        $now = now();

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

        $seeders = Peer::query()
            ->where('torrent_id', $torrent->id)
            ->where('is_seeder', true)
            ->count();
        $leechers = Peer::query()
            ->where('torrent_id', $torrent->id)
            ->where('is_seeder', false)
            ->count();

        $torrent->forceFill([
            'seeders' => $seeders,
            'leechers' => $leechers,
        ])->save();

        $numwant = isset($data['numwant']) ? (int) $data['numwant'] : 50;
        $numwant = max(1, min($numwant, 100));

        $payload = [
            'complete' => $seeders,
            'incomplete' => $leechers,
            'interval' => 900,
            'peers' => $this->peersForResponse($torrent->id, $peerId, $numwant),
        ];

        return $this->success($payload);
    }

    private function peersForResponse(int $torrentId, string $excludingPeer, int $limit): array
    {
        return Peer::query()
            ->where('torrent_id', $torrentId)
            ->where('peer_id', '!=', $excludingPeer)
            ->orderByDesc('last_announce_at')
            ->limit($limit)
            ->get(['peer_id', 'ip', 'port'])
            ->map(static fn (Peer $peer): array => [
                'peer id' => $peer->peer_id,
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
