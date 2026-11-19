<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            'sources_by_media_type' => ['movie' => 'tmdb', 'tv' => 'none', 'book' => 'open_library', 'music' => 'musicbrainz'],
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
        $response->assertJsonPath('sources_by_media_type.book', 'open_library');
        $config = app(ExternalMetadataConfig::class);
        $this->assertSame(['tmdb'], $config->providerPriorityFor('movie'));
        $this->assertSame([], $config->providerPriorityFor('tv'));
        $this->assertSame(['open_library'], $config->providerPriorityFor('book'));
        $this->assertSame(['musicbrainz'], $config->providerPriorityFor('music'));

        $payload['sources_by_media_type']['book'] = 'tmdb';
        $this->actingAs($admin)->postJson('/api/admin/settings/metadata/providers', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['sources_by_media_type.book']);
    }

    public function test_sysop_can_override_and_restore_the_media_source_for_one_torrent(): void
    {
        Queue::fake();
        $torrent = Torrent::factory()->create(['type' => 'music']);
        $other = Torrent::factory()->create(['type' => 'music']);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $url = route('sysop.torrents.metadata-source', ['torrent' => $torrent->id]);

        $this->actingAs($admin)->post($url, ['source' => 'musicbrainz'])->assertForbidden();
        $this->actingAs($sysop)->postJson($url, ['source' => 'tmdb'])->assertUnprocessable();
        $this->post($url, ['source' => 'musicbrainz'])->assertRedirect(route('torrents.show', $torrent));
        $config = app(ExternalMetadataConfig::class);
        $this->assertSame(['musicbrainz'], $config->providerPriorityFor('music', (int) $torrent->id));
        $this->assertSame($config->providerPriority(), $config->providerPriorityFor('music', (int) $other->id));
        Queue::assertPushed(EnrichTorrentExternalMetadata::class);

        $this->post($url, ['source' => 'default'])->assertRedirect();
        $this->assertSame($config->providerPriority(), $config->providerPriorityFor('music', (int) $torrent->id));
    }

    public function test_only_sysop_can_publish_or_disable_licensed_rotten_tomatoes_scores(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $url = '/api/admin/settings/metadata/rotten-tomatoes/tt0133093';
        $rating = ['enabled' => true, 'slug' => 'the_matrix', 'critics' => 0, 'audience' => 90];

        $this->actingAs($admin)->putJson($url, $rating)->assertForbidden();
        $this->actingAs($sysop)->putJson($url, $rating)->assertOk();
        $this->assertSame([
            'url' => 'https://www.rottentomatoes.com/m/the_matrix',
            'critics' => 0,
            'audience' => 90,
        ], app(ExternalMetadataConfig::class)->rottenTomatoesRatings('tt0133093'));

        $this->putJson($url, ['enabled' => false])->assertOk();
        $this->assertNull(app(ExternalMetadataConfig::class)->rottenTomatoesRatings('tt0133093'));
    }
}
