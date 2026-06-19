<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsReviewQueueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_review_queue_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/operations-review-queue', route('api.discovery.operations-review-queue', [], false));
    }

    public function test_discovery_operations_review_queue_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-review-queue'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_catalog_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-review-queue'))
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
            ->assertJsonPath('filters.severity', null)
            ->assertJsonPath('filters.available_fields.0', 'category')
            ->assertJsonPath('filters.available_statuses.0', 'discovery_ready')
            ->assertJsonPath('filters.available_priorities.0', 'missing_core_metadata')
            ->assertJsonPath('filters.available_severities.0', 'critical')
            ->assertJsonPath('summary.total_queue_items', 0)
            ->assertJsonPath('queue', []);
    }

    public function test_filters_return_matching_review_queue_items(): void
    {
        $this->seedReviewQueueCatalog();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-review-queue', ['field' => 'category']))
            ->assertOk()
            ->assertJsonPath('filters.field', 'category')
            ->assertJsonPath('summary.total_queue_items', 1)
            ->assertJsonPath('queue.0.torrent_name', 'Missing Category Movie')
            ->assertJsonPath('queue.0.metadata_field', 'category');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-review-queue', ['status' => 'weakly_discoverable']))
            ->assertOk()
            ->assertJsonPath('filters.status', 'weakly_discoverable')
            ->assertJsonPath('queue.0.torrent_name', 'Weak Source Movie');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-review-queue', ['priority' => 'weak_audio_subtitle_source_coverage']))
            ->assertOk()
            ->assertJsonPath('filters.priority', 'weak_audio_subtitle_source_coverage')
            ->assertJsonPath('queue.0.metadata_field', 'source');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-review-queue', ['severity' => 'critical']))
            ->assertOk()
            ->assertJsonPath('filters.severity', 'critical')
            ->assertJsonPath('queue.0.severity', 'critical');
    }

    public function test_combined_field_and_status_filter_returns_matching_review_queue_items(): void
    {
        $this->seedReviewQueueCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-review-queue', ['field' => 'source', 'status' => 'weakly_discoverable']))
            ->assertOk()
            ->assertJsonPath('summary.total_queue_items', 1)
            ->assertJsonPath('queue.0.torrent_name', 'Weak Source Movie')
            ->assertJsonPath('queue.0.metadata_field', 'source');
    }

    public function test_invalid_field_status_priority_and_severity_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.discovery.operations-review-queue', ['field' => 'score']))->assertUnprocessable()->assertJsonValidationErrors('field');
        $this->actingAs($user)->getJson(route('api.discovery.operations-review-queue', ['status' => 'ranked']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($user)->getJson(route('api.discovery.operations-review-queue', ['priority' => 'popular']))->assertUnprocessable()->assertJsonValidationErrors('priority');
        $this->actingAs($user)->getJson(route('api.discovery.operations-review-queue', ['severity' => 'emergency']))->assertUnprocessable()->assertJsonValidationErrors('severity');
    }

    public function test_discovery_operations_review_queue_response_shape_remains_stable(): void
    {
        $this->seedReviewQueueCatalog();

        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-review-queue'))
            ->assertOk()
            ->json();

        $this->assertSame(['version', 'readonly', 'metadata_first', 'personalized', 'uses_user_history', 'uses_download_history', 'uses_watch_history', 'filters', 'summary', 'queue'], array_keys($payload));
        $this->assertSame(['field', 'status', 'priority', 'severity', 'available_fields', 'available_statuses', 'available_priorities', 'available_severities'], array_keys($payload['filters']));
        $this->assertSame(['total_queue_items', 'field', 'status', 'priority', 'severity', 'critical_items', 'warning_items', 'note_items', 'info_items', 'recommended_staff_focus'], array_keys($payload['summary']));
        $this->assertSame(['id', 'torrent_id', 'torrent_name', 'discovery_status', 'metadata_field', 'priority_type', 'severity', 'issue_title', 'issue_summary', 'explanation', 'recommended_staff_action', 'action_hint_type', 'readonly', 'mutation_allowed'], array_keys($payload['queue'][0]));
    }

    public function test_review_queue_remains_readonly_and_does_not_mutate_metadata(): void
    {
        $this->seedReviewQueueCatalog();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-review-queue'))
            ->assertOk()
            ->assertJsonPath('queue.0.mutation_allowed', false)
            ->assertJsonStructure(['queue' => [['recommended_staff_action', 'explanation']]]);

        $this->assertDatabaseCount('torrent_metadata', 2);
    }

    private function seedReviewQueueCatalog(): void
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
