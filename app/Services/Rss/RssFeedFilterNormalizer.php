<?php

declare(strict_types=1);

namespace App\Services\Rss;

final class RssFeedFilterNormalizer
{
    public const MAX_LIMIT = 100;

    public const DEFAULT_LIMIT = 50;

    /** @var list<string> */
    public const SUPPORTED_KEYS = [
        'q',
        'type',
        'resolution',
        'source',
        'release_group',
        'freeleech',
        'category',
        'language',
        'audio_language',
        'subtitle_language',
        'subtitles',
        'limit',
    ];

    /** @var list<string> */
    private const STRING_KEYS = [
        'q',
        'type',
        'resolution',
        'source',
        'release_group',
        'language',
        'audio_language',
        'subtitle_language',
        'subtitles',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit: int}
     */
    public function normalize(array $input): array
    {
        $safe = array_intersect_key($input, array_flip(self::SUPPORTED_KEYS));
        $normalized = [];

        foreach (self::STRING_KEYS as $key) {
            $value = $safe[$key] ?? '';
            $normalized[$key] = is_scalar($value) ? trim((string) $value) : '';
        }

        $freeleech = $safe['freeleech'] ?? null;
        $category = $safe['category'] ?? null;
        $limit = $safe['limit'] ?? self::DEFAULT_LIMIT;

        return [
            'q' => $normalized['q'],
            'type' => $normalized['type'],
            'resolution' => $normalized['resolution'],
            'source' => $normalized['source'],
            'release_group' => $normalized['release_group'],
            'language' => $normalized['language'],
            'audio_language' => $normalized['audio_language'],
            'subtitle_language' => $normalized['subtitle_language'],
            'subtitles' => $normalized['subtitles'],
            'freeleech' => $freeleech === null || $freeleech === '' ? null : filter_var($freeleech, FILTER_VALIDATE_BOOLEAN),
            'category' => $category === null || $category === '' ? null : (int) $category,
            'limit' => min(self::MAX_LIMIT, max(1, (int) $limit)),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizedForStorage(array $input): array
    {
        $filters = $this->normalize($input);

        return array_filter(
            $filters,
            static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== self::DEFAULT_LIMIT
        );
    }
}
