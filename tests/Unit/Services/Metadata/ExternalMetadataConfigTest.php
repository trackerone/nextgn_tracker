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
        SiteSetting::query()->insert([
            ['key' => 'metadata.enrichment.enabled', 'value' => 'false', 'type' => 'bool', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'metadata.enrichment.auto_on_publish', 'value' => 'false', 'type' => 'bool', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'metadata.enrichment.refresh_after_days', 'value' => '7', 'type' => 'int', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'metadata.providers.tmdb.enabled', 'value' => 'false', 'type' => 'bool', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'metadata.providers.priority', 'value' => '["trakt","tmdb"]', 'type' => 'json', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $config = app(ExternalMetadataConfig::class);

        $this->assertFalse($config->enrichmentEnabled());
        $this->assertFalse($config->autoOnPublishEnabled());
        $this->assertSame(7, $config->refreshAfterDays());
        $this->assertFalse($config->providerEnabled('tmdb'));
        $this->assertSame(['trakt', 'tmdb'], $config->providerPriority());
    }
}
