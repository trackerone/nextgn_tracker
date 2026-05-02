<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Services\BencodeService;
use Illuminate\Support\Str;

final class TorrentMetadataExtractor
{
    public function __construct(
        private readonly BencodeService $bencode,
    ) {}

    public function extract(string $torrentPayload, ?string $rawNfo = null): TorrentExtractedMetadata
    {
        $decoded = $this->bencode->decode($torrentPayload);
        $infoName = null;

        if (is_array($decoded)) {
            $info = $decoded['info'] ?? null;
            if (is_array($info) && is_string($info['name'] ?? null)) {
                $infoName = trim($info['name']);
            }
        }

        $normalizedNfo = $this->normalizeNfo($rawNfo);
        $titleFromName = $this->extractTitleFromReleaseName($infoName);
        $year = $this->extractYear($normalizedNfo) ?? $this->extractYear($infoName);

        if ($normalizedNfo === null) {
            $imdbId = $this->extractImdbId($infoName ?? '');
            $tmdbId = $this->extractTmdbId($infoName ?? '');

            return new TorrentExtractedMetadata(
                title: $titleFromName,
                year: $year,
                resolution: $this->extractResolution($infoName),
                source: $this->extractSource($infoName),
                releaseGroup: $this->extractReleaseGroup($infoName),
                imdbId: $imdbId,
                imdbUrl: $imdbId !== null ? sprintf('https://www.imdb.com/title/%s/', $imdbId) : null,
                tmdbId: $tmdbId,
                tmdbUrl: $tmdbId !== null ? sprintf('https://www.themoviedb.org/movie/%s', $tmdbId) : null,
                rawNfo: null,
                rawName: $infoName,
                parsedName: $titleFromName,
            );
        }

        $imdbId = $this->extractImdbId($normalizedNfo);
        $tmdbId = $this->extractTmdbId($normalizedNfo);
        $title = $this->extractTitle($normalizedNfo) ?? $titleFromName;

        return new TorrentExtractedMetadata(
            title: $title,
            year: $year,
            resolution: $this->extractResolution($normalizedNfo) ?? $this->extractResolution($infoName),
            source: $this->extractSource($normalizedNfo) ?? $this->extractSource($infoName),
            releaseGroup: $this->extractReleaseGroup($normalizedNfo) ?? $this->extractReleaseGroup($infoName),
            imdbId: $imdbId,
            imdbUrl: $imdbId !== null ? sprintf('https://www.imdb.com/title/%s/', $imdbId) : null,
            tmdbId: $tmdbId,
            tmdbUrl: $tmdbId !== null ? sprintf('https://www.themoviedb.org/movie/%s', $tmdbId) : null,
            rawNfo: $normalizedNfo,
            rawName: $infoName,
            parsedName: $title,
        );
    }

    /**
     * @param  array<int, array{path?: string, content?: string|null}>  $nfoDocuments
     */
    public function selectPreferredNfo(array $nfoDocuments): ?string
    {
        if ($nfoDocuments === []) {
            return null;
        }

        usort($nfoDocuments, function (array $left, array $right): int {
            return $this->scoreNfoPath((string) ($right['path'] ?? '')) <=> $this->scoreNfoPath((string) ($left['path'] ?? ''));
        });

        foreach ($nfoDocuments as $nfoDocument) {
            $normalized = $this->normalizeNfo($nfoDocument['content'] ?? null);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function scoreNfoPath(string $path): int
    {
        $normalized = Str::of(str_replace('\\', '/', $path))->lower()->toString();

        $score = 0;

        if ($normalized === '' || str_contains($normalized, '/sample/')) {
            return -100;
        }

        if (str_contains($normalized, '/proof/')) {
            $score -= 10;
        }

        if (str_ends_with($normalized, '.nfo')) {
            $score += 5;
        }

        if (! str_contains($normalized, '/')) {
            $score += 3;
        }

        if (str_contains($normalized, 'readme')) {
            $score -= 5;
        }

        return $score;
    }

    private function normalizeNfo(?string $rawNfo): ?string
    {
        if ($rawNfo === null) {
            return null;
        }

        $normalized = preg_replace("/\r\n?/", "\n", $rawNfo) ?? $rawNfo;
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }

    private function extractImdbId(string $text): ?string
    {
        if (preg_match('/\b(tt\d{7,8})\b/i', $text, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function extractTmdbId(string $text): ?string
    {
        $patterns = [
            '/themoviedb\.org\/(?:movie|tv)\/(\d{2,})/i',
            '/tmdb(?:id)?[:\s#-]*(\d{2,})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractTitle(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/^\s*title\s*[:=-]\s*(.+)$/im', $text, $matches) === 1) {
            $value = trim($matches[1]);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function extractYear(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b((?:19|20)\d{2})\b/', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractResolution(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(2160p|1080p|720p|480p)\b/i', $text, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function extractSource(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(WEB[ .-]?DL|WEB[ .-]?RIP|BLU[ .-]?RAY|HDTV|DVDRIP)\b/i', $text, $matches) === 1) {
            return strtoupper(str_replace([' ', '.'], '', $matches[1]));
        }

        return null;
    }

    private function extractReleaseGroup(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/-([A-Za-z0-9][A-Za-z0-9._-]{1,15})\s*$/m', trim($text), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function extractTitleFromReleaseName(?string $releaseName): ?string
    {
        if ($releaseName === null || $releaseName === '') {
            return null;
        }

        $parts = preg_split('/[.\-_]/', $releaseName) ?: [];
        $titleParts = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(19|20)\d{2}$/', $part) === 1 || preg_match('/^\d{3,4}p$/i', $part) === 1) {
                break;
            }

            if (preg_match('/^(web|bluray|hdtv|dvdrip)$/i', $part) === 1) {
                break;
            }

            $titleParts[] = $part;
        }

        if ($titleParts === []) {
            return null;
        }

        return trim(implode(' ', $titleParts));
    }
}
