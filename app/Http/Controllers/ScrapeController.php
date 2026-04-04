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

        foreach ($hashes as $hash) {
            $keyHex = $this->toInfoHashHexKey($hash);
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
     * Return every info_hash query value, including indexed array parameters.
     */
    private function allInfoHashParams(Request $request): array
    {
        $queryString = (string) $request->getQueryString();

        if ($queryString === '') {
            return [];
        }

        $hashes = [];

        if (preg_match_all('/(?:^|&)info_hash(?:%5B[^&=]*%5D|\[[^&=]*\])?=([^&]*)/i', $queryString, $matches) > 0) {
            foreach ($matches[1] as $rawValue) {
                $hashes[] = rawurldecode((string) $rawValue);
            }
        }

        if ($hashes !== []) {
            return $hashes;
        }

        $parsedValue = $request->query('info_hash');

        if (is_array($parsedValue)) {
            return array_values(array_filter($parsedValue, static fn (mixed $value): bool => is_string($value)));
        }

        return is_string($parsedValue) ? [$parsedValue] : [];
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
