<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsCommandCenterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_command_center_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/operations-command-center', route('api.discovery.operations-command-center', [], false));
    }

    public function test_discovery_operations_command_center_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-command-center'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_catalog_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-command-center'))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('filters.field', null)
            ->assertJsonPath('filters.available_fields.0', 'category')
            ->assertJsonPath('filters.available_statuses.0', 'discovery_ready')
            ->assertJsonPath('filters.available_priorities.0', 'missing_core_metadata')
            ->assertJsonPath('filters.available_severities.0', 'critical')
            ->assertJsonPath('summary.total_visible_torrents', 0)
            ->assertJsonPath('summary.total_queue_items', 0)
            ->assertJsonPath('next_staff_focus.source', 'none')
            ->assertJsonPath('next_staff_focus.severity', 'info');
    }

    public function test_filters_return_matching_command_center_items(): void
    {
        $this->seedCommandCenterCatalog();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['field' => 'category']))->assertOk()->assertJsonPath('filters.field', 'category')->assertJsonPath('review_queue.0.metadata_field', 'category');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['status' => 'weakly_discoverable']))->assertOk()->assertJsonPath('filters.status', 'weakly_discoverable')->assertJsonPath('review_queue.0.torrent_name', 'Weak Source Movie');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['priority' => 'weak_audio_subtitle_source_coverage']))->assertOk()->assertJsonPath('filters.priority', 'weak_audio_subtitle_source_coverage')->assertJsonPath('review_queue.0.metadata_field', 'source');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['severity' => 'critical']))->assertOk()->assertJsonPath('filters.severity', 'critical')->assertJsonPath('review_queue.0.severity', 'critical');
    }

    public function test_combined_field_and_status_filter_returns_matching_command_center_items(): void
    {
        $this->seedCommandCenterCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-command-center', ['field' => 'source', 'status' => 'weakly_discoverable']))
            ->assertOk()
            ->assertJsonPath('summary.total_queue_items', 1)
            ->assertJsonPath('review_queue.0.torrent_name', 'Weak Source Movie');
    }

    public function test_invalid_field_status_priority_and_severity_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['field' => 'score']))->assertUnprocessable()->assertJsonValidationErrors('field');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['status' => 'ranked']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['priority' => 'popular']))->assertUnprocessable()->assertJsonValidationErrors('priority');
        $this->actingAs($user)->getJson(route('api.discovery.operations-command-center', ['severity' => 'emergency']))->assertUnprocessable()->assertJsonValidationErrors('severity');
    }

    public function test_command_center_response_shape_remains_stable_and_readonly(): void
    {
        $this->seedCommandCenterCatalog();

        $payload = $this->actingAs(User::factory()->create())->getJson(route('api.discovery.operations-command-center'))->assertOk()->json();

        $this->assertSame(['version', 'readonly', 'metadata_first', 'personalized', 'uses_user_history', 'uses_download_history', 'uses_watch_history', 'filters', 'summary', 'health', 'overview', 'priorities', 'action_hints', 'review_queue', 'next_staff_focus'], array_keys($payload));
        $this->assertSame(['field', 'status', 'priority', 'severity', 'available_fields', 'available_statuses', 'available_priorities', 'available_severities'], array_keys($payload['filters']));
        $this->assertSame(['total_visible_torrents', 'discovery_readiness_rate', 'total_priorities', 'total_action_hints', 'total_queue_items', 'critical_queue_items', 'warning_queue_items', 'note_queue_items', 'info_queue_items', 'highest_severity', 'recommended_staff_focus'], array_keys($payload['summary']));
        $this->assertArrayHasKey('metrics', $payload['health']);
        $this->assertArrayHasKey('summary', $payload['overview']);
        $this->assertSame(['type', 'severity', 'title', 'message', 'reason', 'recommended_staff_action'], array_keys($payload['priorities'][0]));
        $this->assertSame(['id', 'type', 'severity', 'title', 'recommended_staff_action', 'reason', 'readonly', 'mutation_allowed'], array_keys($payload['action_hints'][0]));
        $this->assertSame(['id', 'torrent_id', 'torrent_name', 'discovery_status', 'metadata_field', 'priority_type', 'severity', 'issue_title', 'issue_summary', 'explanation', 'recommended_staff_action', 'action_hint_type', 'readonly', 'mutation_allowed'], array_keys($payload['review_queue'][0]));
        $this->assertSame(['severity', 'title', 'recommended_staff_action', 'reason', 'source'], array_keys($payload['next_staff_focus']));
        $this->assertFalse($payload['action_hints'][0]['mutation_allowed']);
        $this->assertDatabaseCount('torrent_metadata', 2);
    }

    private function seedCommandCenterCatalog(): void
    {
        $category = Category::factory()->create(['name' => 'Movies']);
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
