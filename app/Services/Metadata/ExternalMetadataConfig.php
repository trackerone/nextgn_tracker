<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Settings\SiteSettingsRepository;

final class ExternalMetadataConfig
{
    public const SOURCE_OPTIONS = [
        'movie' => ['default', 'tmdb', 'trakt', 'imdb', 'none'],
        'tv' => ['default', 'tmdb', 'trakt', 'imdb', 'tvmaze', 'none'],
        'book' => ['default', 'open_library', 'none'],
        'music' => ['default', 'musicbrainz', 'none'],
    ];

    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly MetadataCredentialsRepository $credentials,
    ) {}

    public function enrichmentEnabled(): bool
    {
        return $this->settings->getBool('metadata.enrichment.enabled');
    }

    public function autoOnPublishEnabled(): bool
    {
        return $this->settings->getBool('metadata.enrichment.auto_on_publish');
    }

    public function refreshAfterDays(): int
    {
        return max(1, $this->settings->getInt('metadata.enrichment.refresh_after_days'));
    }

    public function providerEnabled(string $provider): bool
    {
        return $this->settings->getBool(sprintf('metadata.providers.%s.enabled', $provider));
    }

    /** @return array{url: string, critics: ?int, audience: ?int}|null */
    public function rottenTomatoesRatings(mixed $imdbId): ?array
    {
        if (! is_string($imdbId) || preg_match('/^tt[0-9]{7,10}$/D', $imdbId) !== 1) {
            return null;
        }

        $rating = $this->settings->getJson('metadata.rotten_tomatoes.'.$imdbId);
        $slug = $rating['slug'] ?? null;
        if (($rating['enabled'] ?? false) !== true || ! is_string($slug) || preg_match('/^[a-z0-9_-]{1,150}$/D', $slug) !== 1) {
            return null;
        }

        $critics = $rating['critics'] ?? null;
        $audience = $rating['audience'] ?? null;

        return [
            'url' => 'https://www.rottentomatoes.com/m/'.$slug,
            'critics' => is_int($critics) ? $critics : null,
            'audience' => is_int($audience) ? $audience : null,
        ];
    }

    public function tmdbApiKey(): ?string
    {
        return $this->credentials->getSecret('metadata.providers.tmdb.api_key', config('metadata.tmdb.api_key'));
    }

    public function traktClientId(): ?string
    {
        return $this->credentials->getSecret('metadata.providers.trakt.client_id', config('metadata.trakt.client_id'));
    }

    public function traktClientSecret(): ?string
    {
        return $this->credentials->getSecret('metadata.providers.trakt.client_secret', config('metadata.trakt.client_secret'));
    }

    public static function mediaType(?string $type, ?string $category = null): ?string
    {
        return match ($type) {
            'movie' => 'movie',
            'tv', 'series', 'episode', 'tv_episode' => 'tv',
            'book', 'ebook', 'audiobook' => 'book',
            'music' => 'music',
            null, 'other' => match ($category) {
                'movies' => 'movie',
                'tv', 'series' => 'tv',
                'books', 'ebooks', 'audiobooks' => 'book',
                'music' => 'music',
                default => $type,
            },
            default => $type,
        };
    }

    /** @return array<string, string> */
    public function sourcesByMediaType(): array
    {
        $stored = $this->settings->getJson('metadata.providers.sources_by_media_type');
        $sources = [];

        foreach (self::SOURCE_OPTIONS as $type => $options) {
            $selection = $stored[$type] ?? null;
            $sources[$type] = is_string($selection) && in_array($selection, $options, true) ? $selection : 'default';
        }

        return $sources;
    }

    public function torrentSourceOverride(int $torrentId, ?string $mediaType): string
    {
        $source = $this->settings->getString('metadata.providers.torrent.'.$torrentId);
        $options = self::SOURCE_OPTIONS[$mediaType ?? ''] ?? [];

        return $source !== 'default' && in_array($source, $options, true) ? $source : 'default';
    }

    public function sourceFor(?string $mediaType, ?int $torrentId = null): string
    {
        $override = $torrentId === null ? 'default' : $this->torrentSourceOverride($torrentId, $mediaType);

        return $override !== 'default' ? $override : ($this->sourcesByMediaType()[$mediaType ?? ''] ?? 'default');
    }

    /** @return list<string> */
    public function providerPriorityFor(?string $mediaType, ?int $torrentId = null): array
    {
        $source = $this->sourceFor($mediaType, $torrentId);

        return match ($source) {
            'none' => [],
            'default' => $this->providerPriority(),
            default => [$source],
        };
    }

    /**
     * @return list<string>
     */
    public function providerPriority(): array
    {
        $priority = $this->settings->getJson('metadata.providers.priority');

        $allowed = ['tmdb', 'trakt', 'imdb'];

        $priority = array_values(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? strtolower($value) : '', $priority),
            static fn (string $value): bool => in_array($value, $allowed, true),
        ));

        if ($priority === []) {
            return ['tmdb', 'trakt', 'imdb'];
        }

        return $priority;
    }
}
