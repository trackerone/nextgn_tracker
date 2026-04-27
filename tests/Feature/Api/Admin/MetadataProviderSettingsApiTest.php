<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetadataProviderSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_metadata_provider_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $payload = [
            'enrichment_enabled' => true,
            'auto_on_publish' => false,
            'refresh_after_days' => 14,
            'providers' => [
                'tmdb' => ['enabled' => true],
                'trakt' => ['enabled' => false],
                'imdb' => ['enabled' => true],
            ],
            'priority' => ['imdb', 'tmdb', 'trakt'],
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/settings/metadata/providers', $payload);

        $response->assertOk();
        $response->assertJsonPath('enrichment_enabled', true);
        $response->assertJsonPath('auto_on_publish', false);
        $response->assertJsonPath('refresh_after_days', 14);
        $response->assertJsonPath('providers.trakt.enabled', false);
        $response->assertJsonPath('priority.0', 'imdb');

        $this->assertDatabaseHas('site_settings', ['key' => 'metadata.providers.priority']);
    }
}
