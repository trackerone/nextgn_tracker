<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Settings\SiteSettingsRepository;

final class ExternalMetadataConfig
{
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
