<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TorrentRepositoryInterface;
use App\Services\BencodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ScrapeController extends Controller
{
    public function __construct(
        private readonly BencodeService $bencode,
        private readonly TorrentRepositoryInterface $torrents,
    ) {}

    public function __invoke(Request $request): Response
    {
        $hashes = $request->query('info_hash');

        $list = [];
        if ($hashes === null) {
            $list = [];
        } elseif (is_array($hashes)) {
            $list = $hashes;
        } else {
            $list = [$hashes];
        }

        /** @var array<string, array{complete:int,downloaded:int,incomplete:int}> $files */
        $files = [];

        foreach ($list as $h) {
            if (! is_string($h)) {
                continue;
            }

            $keyHex = $this->toInfoHashHexKey($h);
            if ($keyHex === null) {
                continue;
            }

            // Force associative keys (never $files[])
            $torrent = $this->torrents->findByInfoHash(strtolower($keyHex))
                ?? $this->torrents->findByInfoHash(strtoupper($keyHex));

            if ($torrent === null) {
                $files[$keyHex] = [
                    'complete' => 0,
                    'downloaded' => 0,
                    'incomplete' => 0,
                ];
                continue;
            }

            $files[$keyHex] = [
                'complete' => (int) $torrent->seeders,
                'downloaded' => (int) $torrent->completed,
                'incomplete' => (int) $torrent->leechers,
            ];
        }

        // Guarantee dictionary encoding (never list):
        // Even if empty, keep as associative map.
        $payload = ['files' => $files];

        return response(
            $this->bencode->encode($payload),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }

    private function toInfoHashHexKey(string $value): ?string
    {
        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $value) === 1) {
            return strtoupper($value);
        }

        if (preg_match('/%[0-9A-Fa-f]{2}/', $value) === 1) {
            $decoded = rawurldecode($value);
            if (strlen($decoded) !== 20) {
                return null;
            }

            return strtoupper(bin2hex($decoded));
        }

        if (strlen($value) === 20) {
            return strtoupper(bin2hex($value));
        }

        return null;
    }
}
