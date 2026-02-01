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
        $hashes = $this->allInfoHashParams($request);

        /** @var array<string, array{complete:int,downloaded:int,incomplete:int}> $files */
        $files = [];

        foreach ($hashes as $h) {
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
     * Return ALL occurrences of info_hash in the raw query string:
     * supports:
     * - ?info_hash=AAA&info_hash=BBB
     * - ?info_hash[]=AAA&info_hash[]=BBB
     */
    private function allInfoHashParams(Request $request): array
    {
        $qs = (string) $request->getQueryString();
        if ($qs === '') {
            return [];
        }

        $out = [];

        // Match both info_hash=... and info_hash[]=...
        if (preg_match_all('/(?:^|&)(info_hash(?:%5B%5D|\[\])?)=([^&]*)/i', $qs, $m) === 1) {
            // preg_match_all returns 1+ only if pattern found;
            // but in PHP it returns number of matches, so handle below:
        }

        if (preg_match_all('/(?:^|&)(info_hash(?:%5B%5D|\[\])?)=([^&]*)/i', $qs, $matches) > 0) {
            foreach ($matches[2] as $rawVal) {
                $out[] = rawurldecode((string) $rawVal);
            }
        }

        // Fallback (if no duplicates): use Laravel’s normal parsing
        if ($out === []) {
            $v = $request->query('info_hash');
            if (is_array($v)) {
                foreach ($v as $vv) {
                    if (is_string($vv)) {
                        $out[] = $vv;
                    }
                }
            } elseif (is_string($v)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Return 40-char UPPERCASE hex string key.
     * Supports:
     * - 40 hex
     * - raw 20 bytes
     */
    private function toInfoHashHexKey(string $value): ?string
    {
        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $value) === 1) {
            return strtoupper($value);
        }

        if (strlen($value) === 20) {
            return strtoupper(bin2hex($value));
        }

        return null;
    }
}
