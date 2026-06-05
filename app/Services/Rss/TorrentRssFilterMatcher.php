<?php

declare(strict_types=1);

namespace App\Services\Rss;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;

final class TorrentRssFilterMatcher
{
    /**
     * @var array<string, string>
     */
    private const LANGUAGE_ALIASES = [
        'da' => 'da',
        'danish' => 'da',
        'dansk' => 'da',
        'no' => 'no',
        'norwegian' => 'no',
        'norsk' => 'no',
        'nb' => 'nb',
        'bokmal' => 'nb',
        'bokmaal' => 'nb',
        'nn' => 'nn',
        'nynorsk' => 'nn',
        'sv' => 'sv',
        'swedish' => 'sv',
        'svensk' => 'sv',
        'fi' => 'fi',
        'finnish' => 'fi',
        'suomi' => 'fi',
        'en' => 'en',
        'english' => 'en',
        'engelsk' => 'en',
    ];

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit?: int}  $filters
     */
    public function matches(Torrent $torrent, array $filters): bool
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);

        if ($filters['category'] !== null && (int) $torrent->category_id !== $filters['category']) {
            return false;
        }

        if ($filters['freeleech'] !== null && (bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false) !== $filters['freeleech']) {
            return false;
        }

        foreach (['type', 'resolution', 'source', 'release_group'] as $field) {
            if ($filters[$field] !== '' && mb_strtolower((string) ($metadata[$field] ?? '')) !== mb_strtolower($filters[$field])) {
                return false;
            }
        }

        foreach (['language', 'audio_language', 'subtitle_language'] as $field) {
            if ($filters[$field] !== '' && ! $this->languageFieldMatches((string) ($metadata[$field] ?? ''), $filters[$field])) {
                return false;
            }
        }

        if ($filters['subtitles'] !== '' && ! $this->languageFieldMatches((string) ($metadata['subtitles'] ?? ''), $filters['subtitles'])) {
            return false;
        }

        if ($filters['q'] === '') {
            return true;
        }

        $needle = mb_strtolower($filters['q']);
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) $torrent->name,
            (string) ($metadata['title'] ?? ''),
            (string) ($metadata['release_group'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }

    private function languageFieldMatches(string $metadataValue, string $filterValue): bool
    {
        $metadataLanguages = $this->normalizeLanguages($metadataValue);

        if ($metadataLanguages === []) {
            return false;
        }

        foreach ($this->normalizeLanguages($filterValue) as $filterLanguage) {
            if (in_array($filterLanguage, $metadataLanguages, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function normalizeLanguages(string $value): array
    {
        $normalized = [];

        foreach (preg_split('/[,;\/|]+/', $value) ?: [] as $part) {
            $language = mb_strtolower(trim($part));

            if ($language === '') {
                continue;
            }

            $language = str_replace(['_', '-'], ' ', $language);
            $language = preg_replace('/\s+/', ' ', $language) ?? $language;
            $language = self::LANGUAGE_ALIASES[$language] ?? $language;
            $language = self::LANGUAGE_ALIASES[strtok($language, ' ') ?: $language] ?? $language;
            $normalized[] = $language;
        }

        return array_values(array_unique($normalized));
    }
}
