<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsActionHintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_action_hints_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/operations-action-hints', route('api.discovery.operations-action-hints', [], false));
    }

    public function test_discovery_operations_action_hints_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-action-hints'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_catalog_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints'))
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
            ->assertJsonPath('summary.total_hints', 8)
            ->assertJsonPath('summary.highest_severity', 'critical')
            ->assertJsonPath('action_hints.0.recommended_staff_action', 'Review upload category mapping and curation guidance.')
            ->assertJsonPath('action_hints.0.readonly', true)
            ->assertJsonPath('action_hints.0.mutation_allowed', false);
    }

    public function test_field_filter_returns_matching_action_hints(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints', ['field' => 'source']))
            ->assertOk()
            ->assertJsonPath('filters.field', 'source')
            ->assertJsonPath('summary.field', 'source')
            ->assertJsonPath('action_hints.0.type', 'improve_source_extraction');
    }

    public function test_status_filter_returns_matching_action_hints(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints', ['status' => 'discovery_ready']))
            ->assertOk()
            ->assertJsonPath('filters.status', 'discovery_ready')
            ->assertJsonPath('action_hints.0.type', 'no_action_required');
    }

    public function test_priority_filter_returns_matching_action_hints(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints', ['priority' => 'weak_audio_subtitle_source_coverage']))
            ->assertOk()
            ->assertJsonPath('filters.priority', 'weak_audio_subtitle_source_coverage')
            ->assertJsonPath('action_hints.0.type', 'improve_source_extraction');
    }

    public function test_combined_field_and_status_filter_returns_matching_action_hints(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints', ['field' => 'category', 'status' => 'missing_core_metadata']))
            ->assertOk()
            ->assertJsonPath('summary.field', 'category')
            ->assertJsonPath('summary.status', 'missing_core_metadata')
            ->assertJsonPath('action_hints.0.type', 'review_category_mapping');
    }

    public function test_invalid_field_status_and_priority_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-action-hints', ['field' => 'score']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-action-hints', ['status' => 'ranked']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($user)
            ->getJson(route('api.discovery.operations-action-hints', ['priority' => 'popular']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_discovery_operations_action_hints_response_shape_remains_stable(): void
    {
        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints'))
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
            'action_hints',
        ], array_keys($payload));

        $this->assertSame([
            'total_hints',
            'field',
            'status',
            'priority',
            'recommended_staff_focus',
            'highest_severity',
        ], array_keys($payload['summary']));

        $this->assertSame([
            'id',
            'type',
            'severity',
            'title',
            'description',
            'applies_to_fields',
            'applies_to_statuses',
            'applies_to_priorities',
            'recommended_staff_action',
            'reason',
            'readonly',
            'mutation_allowed',
        ], array_keys($payload['action_hints'][0]));
    }

    public function test_action_hints_remain_readonly_and_do_not_mutate_metadata(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-action-hints'))
            ->assertOk()
            ->assertJsonPath('action_hints.0.mutation_allowed', false)
            ->assertJsonStructure(['action_hints' => [['recommended_staff_action', 'reason']]]);

        $this->assertDatabaseCount('torrent_metadata', 0);
    }
}
