<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryHealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_health_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/health', route('api.discovery.health', [], false));
    }

    public function test_discovery_health_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.health'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_discovery_health_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.health'))
            ->assertOk()
            ->assertExactJson([
                'version' => 1,
                'readonly' => true,
                'metadata_first' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'metrics' => [
                    'total_visible_torrents' => 0,
                    'torrents_with_core_metadata' => 0,
                    'missing_core_metadata_torrents' => 0,
                    'discovery_ready_torrents' => 0,
                    'weakly_discoverable_torrents' => 0,
                    'discovery_readiness_rate' => 0.0,
                ],
                'metadata_coverage' => $this->emptyCoverageContract(),
                'indicators' => [
                    'has_visible_torrents' => false,
                    'has_discovery_ready_torrents' => false,
                    'has_weakly_discoverable_torrents' => false,
                    'has_metadata_gaps' => false,
                    'metadata_first' => true,
                ],
            ]);
    }

    public function test_authenticated_user_can_read_populated_and_partial_discovery_health_contract(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Movies']);

        $ready = Torrent::factory()->create([
            'category_id' => $category->id,
            'type' => 'movie',
            'source' => null,
            'resolution' => null,
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $ready->id,
            'year' => 2026,
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'english',
            'subtitle_language' => 'spanish',
            'release_group' => 'NTB',
        ]);

        $weak = Torrent::factory()->create([
            'category_id' => null,
            'type' => 'movie',
            'source' => null,
            'resolution' => null,
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $weak->id,
            'type' => 'movie',
        ]);

        Torrent::factory()->banned()->create(['category_id' => $category->id]);
        Torrent::factory()->unapproved()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->getJson(route('api.discovery.health'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('metrics.total_visible_torrents', 2)
            ->assertJsonPath('metrics.torrents_with_core_metadata', 1)
            ->assertJsonPath('metrics.missing_core_metadata_torrents', 1)
            ->assertJsonPath('metrics.discovery_ready_torrents', 1)
            ->assertJsonPath('metrics.weakly_discoverable_torrents', 1)
            ->assertJsonPath('metrics.discovery_readiness_rate', 0.5)
            ->assertJsonPath('metadata_coverage.0', [
                'field' => 'category',
                'label' => 'Category',
                'total' => 2,
                'covered' => 1,
                'missing' => 1,
                'coverage_rate' => 0.5,
            ])
            ->assertJsonPath('metadata_coverage.8', [
                'field' => 'year',
                'label' => 'Year',
                'total' => 2,
                'covered' => 1,
                'missing' => 1,
                'coverage_rate' => 0.5,
            ])
            ->assertJsonPath('indicators.has_visible_torrents', true)
            ->assertJsonPath('indicators.has_discovery_ready_torrents', true)
            ->assertJsonPath('indicators.has_weakly_discoverable_torrents', true)
            ->assertJsonPath('indicators.has_metadata_gaps', true)
            ->assertJsonPath('indicators.metadata_first', true);
    }

    public function test_discovery_health_response_shape_remains_stable(): void
    {
        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.health'))
            ->assertOk()
            ->json();

        $this->assertSame([
            'version',
            'readonly',
            'metadata_first',
            'personalized',
            'uses_user_history',
            'uses_download_history',
            'uses_watch_history',
            'metrics',
            'metadata_coverage',
            'indicators',
        ], array_keys($payload));

        $this->assertSame([
            'total_visible_torrents',
            'torrents_with_core_metadata',
            'missing_core_metadata_torrents',
            'discovery_ready_torrents',
            'weakly_discoverable_torrents',
            'discovery_readiness_rate',
        ], array_keys($payload['metrics']));
    }

    public function test_discovery_health_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)->json($method, route('api.discovery.health'))->assertStatus(405);
        }
    }

    private function emptyCoverageContract(): array
    {
        return array_map(
            static fn (array $field): array => [
                'field' => $field[0],
                'label' => $field[1],
                'total' => 0,
                'covered' => 0,
                'missing' => 0,
                'coverage_rate' => 0.0,
            ],
            [
                ['category', 'Category'],
                ['type', 'Type'],
                ['resolution', 'Resolution'],
                ['source', 'Source'],
                ['language', 'Language'],
                ['audio_language', 'Audio Language'],
                ['subtitle_language', 'Subtitle Language'],
                ['release_group', 'Release Group'],
                ['year', 'Year'],
            ],
        );
    }
}
