<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsDrilldownApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_drilldown_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/operations-drilldown', route('api.discovery.operations-drilldown', [], false));
    }

    public function test_discovery_operations_drilldown_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-drilldown'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_catalog_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown'))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('filters.field', null)
            ->assertJsonPath('filters.status', null)
            ->assertJsonPath('filters.priority', null)
            ->assertJsonPath('filters.available_fields.0', 'category')
            ->assertJsonPath('filters.available_statuses.0', 'discovery_ready')
            ->assertJsonPath('filters.available_priorities.0', 'missing_core_metadata')
            ->assertJsonPath('summary.total_matching_torrents', 0)
            ->assertJsonPath('summary.recommended_staff_action', 'Review affected torrents and improve missing metadata coverage.')
            ->assertJsonPath('rows', []);
    }

    public function test_field_filter_returns_matching_affected_torrents(): void
    {
        $this->seedDrilldownCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown', ['field' => 'category']))
            ->assertOk()
            ->assertJsonPath('filters.field', 'category')
            ->assertJsonPath('summary.total_matching_torrents', 1)
            ->assertJsonPath('summary.missing_count', 1)
            ->assertJsonPath('rows.0.torrent_name', 'Missing Category Movie')
            ->assertJsonPath('rows.0.metadata_field', 'category')
            ->assertJsonPath('rows.0.metadata_missing', true)
            ->assertJsonStructure(['rows' => [['recommended_staff_action', 'explanation']]]);
    }

    public function test_status_filter_returns_matching_torrents(): void
    {
        $this->seedDrilldownCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown', ['status' => 'weakly_discoverable']))
            ->assertOk()
            ->assertJsonPath('filters.status', 'weakly_discoverable')
            ->assertJsonPath('summary.total_matching_torrents', 1)
            ->assertJsonPath('rows.0.torrent_name', 'Weak Source Movie')
            ->assertJsonPath('rows.0.discovery_status', 'weakly_discoverable');
    }

    public function test_priority_filter_returns_matching_torrents(): void
    {
        $this->seedDrilldownCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown', ['priority' => 'missing_core_metadata']))
            ->assertOk()
            ->assertJsonPath('filters.priority', 'missing_core_metadata')
            ->assertJsonPath('summary.total_matching_torrents', 1)
            ->assertJsonPath('rows.0.discovery_status', 'missing_core_metadata');
    }

    public function test_combined_field_and_status_filter_returns_matching_torrents(): void
    {
        $this->seedDrilldownCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown', ['field' => 'source', 'status' => 'weakly_discoverable']))
            ->assertOk()
            ->assertJsonPath('summary.total_matching_torrents', 1)
            ->assertJsonPath('rows.0.torrent_name', 'Weak Source Movie')
            ->assertJsonPath('rows.0.metadata_field', 'source');
    }

    public function test_invalid_field_and_status_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-drilldown', ['field' => 'score']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-drilldown', ['status' => 'ranked']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_discovery_operations_drilldown_response_shape_remains_stable(): void
    {
        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-drilldown'))
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
            'filters',
            'summary',
            'rows',
        ], array_keys($payload));

        $this->assertSame([
            'total_matching_torrents',
            'field',
            'status',
            'priority',
            'missing_count',
            'present_count',
            'recommended_staff_action',
        ], array_keys($payload['summary']));
    }

    private function seedDrilldownCatalog(): void
    {
        $category = Category::factory()->create(['name' => 'Movies']);

        $ready = Torrent::factory()->create(['category_id' => $category->id, 'name' => 'Ready Movie']);
        TorrentMetadata::query()->create($this->metadata($ready, ['type' => 'movie', 'resolution' => '1080p', 'source' => 'WEB-DL', 'language' => 'english', 'audio_language' => 'english', 'subtitle_language' => 'spanish', 'release_group' => 'NTB', 'year' => 2026]));

        $missingCategory = Torrent::factory()->create(['category_id' => null, 'name' => 'Missing Category Movie']);
        TorrentMetadata::query()->create($this->metadata($missingCategory, ['type' => 'movie', 'resolution' => '1080p', 'source' => 'WEB-DL', 'language' => 'english', 'audio_language' => 'english']));

        $weakSource = Torrent::factory()->create(['category_id' => $category->id, 'name' => 'Weak Source Movie']);
        TorrentMetadata::query()->create($this->metadata($weakSource, ['type' => 'movie', 'resolution' => '1080p', 'language' => 'english']));
    }

    private function metadata(Torrent $torrent, array $attributes): array
    {
        return array_merge(['torrent_id' => $torrent->id], $attributes);
    }
}
