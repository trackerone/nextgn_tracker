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

        // Normalize to array
        if ($hashes === null) {
            $list = [];
        } elseif (is_array($hashes)) {
            $list = $hashes;
        } else {
            $list = [$hashes];
        }

        $files = [];

        foreach ($list as $h) {
            if (! is_string($h)) {
                continue;
            }

            $keyHex = $this->toInfoHashHexKey($h);
            if ($keyHex === null) {
                continue;
            }

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

        return response(
            $this->bencode->encode(['files' => $files]),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }

    /**
     * Return a 40-char UPPERCASE hex string to be used as the dictionary key.
     * Supports:
     * - 40-char hex
     * - percent-encoded raw 20 bytes
     * - raw 20 bytes
     *
     * IMPORTANT: Never pad/truncate; must not mutate hashes.
     */
    private function toInfoHashHexKey(string $value): ?string
    {
        // Already 40 hex
        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $value) === 1) {
            return strtoupper($value);
        }

        // Percent-encoded bytes
        if (preg_match('/%[0-9A-Fa-f]{2}/', $value) === 1) {
            $decoded = rawurldecode($value);
            if (strlen($decoded) !== 20) {
                return null;
            }

            return strtoupper(bin2hex($decoded));
        }

        // Raw bytes
        if (strlen($value) === 20) {
            return strtoupper(bin2hex($value));
        }

        return null;
    }
}
