<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $now = now();

        $settings = [
            [
                'key' => 'metadata.providers.tmdb.enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'metadata_providers',
                'label' => 'TMDb provider enabled',
                'description' => 'Enable external metadata enrichment via TMDb.',
            ],
            [
                'key' => 'metadata.providers.trakt.enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'metadata_providers',
                'label' => 'Trakt provider enabled',
                'description' => 'Enable external metadata enrichment via Trakt.',
            ],
            [
                'key' => 'metadata.providers.imdb.enabled',
                'value' => 'false',
                'type' => 'bool',
                'group' => 'metadata_providers',
                'label' => 'IMDb provider enabled',
                'description' => 'Enable external metadata enrichment via IMDb official contracts.',
            ],
            [
                'key' => 'metadata.providers.priority',
                'value' => json_encode(['tmdb', 'trakt', 'imdb'], JSON_THROW_ON_ERROR),
                'type' => 'json',
                'group' => 'metadata_providers',
                'label' => 'Provider priority',
                'description' => 'Ordered list of provider keys used for metadata enrichment.',
            ],
            [
                'key' => 'metadata.enrichment.enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'metadata_enrichment',
                'label' => 'Metadata enrichment enabled',
                'description' => 'Enable asynchronous external metadata enrichment.',
            ],
            [
                'key' => 'metadata.enrichment.auto_on_publish',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'metadata_enrichment',
                'label' => 'Auto enrich on publish',
                'description' => 'Dispatch metadata enrichment when a torrent is published.',
            ],
            [
                'key' => 'metadata.enrichment.refresh_after_days',
                'value' => '30',
                'type' => 'int',
                'group' => 'metadata_enrichment',
                'label' => 'Refresh after days',
                'description' => 'Suggested refresh interval for external metadata records.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    ...$setting,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->whereIn('key', [
            'metadata.providers.tmdb.enabled',
            'metadata.providers.trakt.enabled',
            'metadata.providers.imdb.enabled',
            'metadata.providers.priority',
            'metadata.enrichment.enabled',
            'metadata.enrichment.auto_on_publish',
            'metadata.enrichment.refresh_after_days',
        ])->delete();
    }
};
