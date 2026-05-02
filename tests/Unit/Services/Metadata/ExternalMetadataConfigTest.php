<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata;

use App\Models\SiteSetting;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExternalMetadataConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_values_from_site_settings_repository(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'false', 'bool');
        $this->setSiteSetting('metadata.enrichment.auto_on_publish', 'false', 'bool');
        $this->setSiteSetting('metadata.enrichment.refresh_after_days', '7', 'int');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'false', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["trakt","tmdb"]', 'json');

        $config = app(ExternalMetadataConfig::class);

        $this->assertFalse($config->enrichmentEnabled());
        $this->assertFalse($config->autoOnPublishEnabled());
        $this->assertSame(7, $config->refreshAfterDays());
        $this->assertFalse($config->providerEnabled('tmdb'));
        $this->assertSame(['trakt', 'tmdb'], $config->providerPriority());
    }

    public function test_credentials_prefer_db_secret_and_fallback_to_config(): void
    {
        config()->set('metadata.tmdb.api_key', 'tmdb-env-key');
        config()->set('metadata.trakt.client_id', 'trakt-env-id');
        config()->set('metadata.trakt.client_secret', 'trakt-env-secret');

        $config = app(ExternalMetadataConfig::class);

        $this->assertSame('tmdb-env-key', $config->tmdbApiKey());
        $this->assertSame('trakt-env-id', $config->traktClientId());
        $this->assertSame('trakt-env-secret', $config->traktClientSecret());

        app(\App\Services\Metadata\MetadataCredentialsRepository::class)
            ->setSecret('metadata.providers.tmdb.api_key', 'tmdb-db-key');

        $this->assertSame('tmdb-db-key', $config->tmdbApiKey());
    }
    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }
}
