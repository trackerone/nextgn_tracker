<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_overview_route_generates_expected_path(): void
    {
        $this->assertSame(
            '/api/discovery/operations-overview',
            route('api.discovery.operations-overview', [], false),
        );
    }

    public function test_discovery_operations_overview_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-overview'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_operations_overview_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-overview'))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('summary.total_visible_torrents', 0)
            ->assertJsonPath('summary.discovery_ready_torrents', 0)
            ->assertJsonPath('summary.weakly_discoverable_torrents', 0)
            ->assertJsonPath('summary.missing_core_metadata_torrents', 0)
            ->assertJsonPath('summary.discovery_readiness_rate', 0.0)
            ->assertJsonPath('attention_items.0.type', 'no_visible_torrents')
            ->assertJsonPath('sample_explanations', []);
    }

    public function test_authenticated_user_can_read_populated_operations_overview_states(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Movies']);

        $ready = Torrent::factory()->create([
            'category_id' => $category->id,
            'name' => 'Ready Movie',
        ]);
        TorrentMetadata::query()->create($this->metadata($ready, [
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'language' => 'english',
            'audio_language' => 'english',
            'subtitle_language' => 'spanish',
            'release_group' => 'NTB',
            'year' => 2026,
        ]));

        $weak = Torrent::factory()->create(['category_id' => null, 'name' => 'Weak Movie']);
        TorrentMetadata::query()->create($this->metadata($weak, ['type' => 'movie']));

        $response = $this->actingAs($user)->getJson(route('api.discovery.operations-overview'));

        $response->assertOk()
            ->assertJsonPath('summary.total_visible_torrents', 2)
            ->assertJsonPath('summary.discovery_ready_torrents', 1)
            ->assertJsonPath('summary.weakly_discoverable_torrents', 1)
            ->assertJsonPath('summary.missing_core_metadata_torrents', 1)
            ->assertJsonPath('summary.discovery_readiness_rate', 0.5)
            ->assertJsonPath('health.metrics.total_visible_torrents', 2)
            ->assertJsonPath('weakest_metadata_fields.0.field', 'category')
            ->assertJsonPath('weakest_metadata_fields.0.label', 'Category')
            ->assertJsonPath('weakest_metadata_fields.0.covered', 1)
            ->assertJsonPath('weakest_metadata_fields.0.missing', 1)
            ->assertJsonPath('weakest_metadata_fields.0.coverage_rate', 0.5)
            ->assertJsonPath('attention_items.0.type', 'missing_core_metadata')
            ->assertJsonPath('sample_explanations.0.torrent_name', 'Weak Movie')
            ->assertJsonStructure([
                'attention_items' => [['type', 'severity', 'message']],
                'sample_explanations' => [[
                    'torrent_id',
                    'torrent_name',
                    'discovery_status',
                    'discovery_summary',
                    'metadata_present',
                    'metadata_missing',
                    'metadata_weak',
                    'explanation',
                ]],
            ]);
    }

    public function test_discovery_operations_overview_response_shape_remains_stable(): void
    {
        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-overview'))
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
            'summary',
            'health',
            'weakest_metadata_fields',
            'attention_items',
            'sample_explanations',
        ], array_keys($payload));

        $this->assertSame([
            'total_visible_torrents',
            'discovery_ready_torrents',
            'weakly_discoverable_torrents',
            'missing_core_metadata_torrents',
            'discovery_readiness_rate',
        ], array_keys($payload['summary']));
    }

    public function test_discovery_operations_overview_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.operations-overview'))
                ->assertStatus(405);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function metadata(Torrent $torrent, array $attributes): array
    {
        return array_merge(['torrent_id' => $torrent->id], $attributes);
    }
}
