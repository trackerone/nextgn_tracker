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
<<<<<< codex/fix-failing-tests-in-recovery-branch-m4yjqz
        $qs = (string) (
            $request->server('QUERY_STRING')
            ?? parse_url((string) $request->getRequestUri(), PHP_URL_QUERY)
            ?? $request->getQueryString()
            ?? ''
        );
=======
        $qs = (string) $request->server('QUERY_STRING', '');
>>>>>> main
        if ($qs === '') {
            return [];
        }

        $out = [];

<<<<<< codex/fix-failing-tests-in-recovery-branch-m4yjqz
        // Match info_hash=..., info_hash[]=..., and indexed keys like info_hash[0]=...
        if (preg_match_all('/(?:^|&)info_hash(?:%5B(?:%5D|[0-9]+%5D)|\[(?:\]|[0-9]+)\])?=([^&]*)/i', $qs, $matches) > 0) {
            /** @var array<int, string> $rawValues */
            $rawValues = $matches[1];

            foreach ($rawValues as $rawVal) {
                $decoded = rawurldecode((string) $rawVal);

                if (strlen($decoded) === 20 || preg_match('/\A[0-9a-fA-F]{40}\z/', $decoded) === 1) {
                    $out[] = $decoded;
                }
=======
        // Match both info_hash=... and info_hash[]=...
        if (preg_match_all('/(?:^|&)(info_hash(?:%5B%5D|\[\])?)=([^&]*)/i', $qs, $matches) > 0) {
            foreach ($matches[2] as $rawVal) {
                $out[] = rawurldecode((string) $rawVal);
>>>>>> main
            }
        }

        if ($out !== []) {
            return $out;
        }

        // Fallback: use Laravel's parsed query values when raw extraction found nothing useful.
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
