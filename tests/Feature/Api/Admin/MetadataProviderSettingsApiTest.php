<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_sysop_can_import_a_film_by_imdb_id_for_ordinary_members(): void
    {
        config([
            'metadata.tmdb.api_key' => 'test-key',
            'metadata.tmdb.base_url' => 'https://api.themoviedb.org/3',
        ]);
        app(SiteSettingsRepository::class)->set('metadata.providers.tmdb.enabled', true, 'bool');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.themoviedb.org/3/find/tt2294449?*' => Http::response([
                'movie_results' => [['id' => 187017, 'title' => '22 Jump Street', 'media_type' => 'movie']],
            ]),
            'https://api.themoviedb.org/3/movie/187017?*' => Http::response([
                'id' => 187017,
                'title' => '22 Jump Street',
                'release_date' => '2014-06-13',
                'overview' => 'An undercover assignment at a college.',
                'poster_path' => '/poster.jpg',
                'credits' => ['cast' => [['name' => 'Jonah Hill', 'character' => 'Schmidt', 'profile_path' => '/hill.jpg']]],
                'videos' => ['results' => [['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'XqMKdHcs5kQ']]],
            ]),
        ]);

        $torrent = Torrent::factory()->create(['type' => 'movie', 'imdb_id' => 'tt2294449', 'tmdb_id' => null]);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create();

        $this->actingAs($sysop)
            ->post(route('sysop.torrents.metadata-source', ['torrent' => $torrent->id]), ['source' => 'tmdb'])
            ->assertRedirect(route('torrents.show', $torrent))
            ->assertSessionHas('metadata_source_status', 'TMDb metadata updated.');

        $this->actingAs($member)->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('22 Jump Street')
            ->assertSee('An undercover assignment at a college.')
            ->assertSee('https://image.tmdb.org/t/p/w500/poster.jpg')
            ->assertSee('Jonah Hill')
            ->assertSee('https://image.tmdb.org/t/p/w185/hill.jpg')
            ->assertSee('data-trailer-id="XqMKdHcs5kQ"', false);
    }

    public function test_sysop_gets_a_clear_warning_when_enrichment_is_disabled(): void
    {
        app(SiteSettingsRepository::class)->set('metadata.enrichment.enabled', false, 'bool');
        Queue::fake();
        Http::fake();

        $torrent = Torrent::factory()->create(['type' => 'movie']);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)
            ->post(route('sysop.torrents.metadata-source', ['torrent' => $torrent->id]), ['source' => 'tmdb'])
            ->assertRedirect(route('torrents.show', $torrent))
            ->assertSessionHas('metadata_source_warning', 'Source saved. Turn on Enable enrichment in Metadata settings, then try again.');

        Queue::assertNothingPushed();
        Http::assertNothingSent();
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
