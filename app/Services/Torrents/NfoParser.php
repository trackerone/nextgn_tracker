<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Services\Security\SanitizationService;

final class NfoParser
{
    public function __construct(
        private readonly SanitizationService $sanitizer,
    )
    {
    }

    /**
     * @return array{sanitized_text: string|null, imdb_id: string|null, tmdb_id: string|null}
     */
    public function parse(?string $rawText): array
    {
        if ($rawText === null) {
            return [
                'sanitized_text' => null,
                'imdb_id' => null,
                'tmdb_id' => null,
            ];
        }

        $normalized = preg_replace("/\r\n?/", "\n", $rawText) ?? $rawText;
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return [
                'sanitized_text' => null,
                'imdb_id' => null,
                'tmdb_id' => null,
            ];
        }

        $sanitized = $this->sanitizer->sanitizeString($normalized);

        if ($sanitized === '') {
            $sanitized = null;
        }

        return [
            'sanitized_text' => $sanitized,
            'imdb_id' => $this->extractImdbId($normalized),
            'tmdb_id' => $this->extractTmdbId($normalized),
        ];
    }

    private function extractImdbId(string $text): ?string
    {
        if (preg_match('/(tt\d{7,8})/i', $text, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function extractTmdbId(string $text): ?string
    {
        $patterns = [
            '/themoviedb\.org\/(?:movie|tv)\/(\d+)/i',
            '/tmdb(?:id)?[:\s#-]*(\d{3,})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
