<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryOperationsPriorityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_operations_priorities_route_generates_expected_path(): void
    {
        $this->assertSame('/api/discovery/operations-priorities', route('api.discovery.operations-priorities', [], false));
    }

    public function test_discovery_operations_priorities_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.operations-priorities'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_priorities_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-priorities'))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('priorities.0.type', 'no_visible_torrents')
            ->assertJsonPath('priorities.0.severity', 'info')
            ->assertJsonPath('priorities.0.recommended_staff_action', 'No action required until visible torrents are available for discovery review.')
            ->assertJsonPath('source_overview.summary.total_visible_torrents', 0)
            ->assertJsonStructure(['priorities' => [[
                'type',
                'severity',
                'title',
                'message',
                'reason',
                'affected_fields',
                'example_torrents',
                'recommended_staff_action',
            ]]]);
    }

    public function test_authenticated_user_can_read_populated_warning_priorities(): void
    {
        $category = Category::factory()->create(['name' => 'Movies']);
        $ready = Torrent::factory()->create(['category_id' => $category->id, 'name' => 'Ready Movie']);
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

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-priorities'))
            ->assertOk()
            ->assertJsonPath('priorities.0.type', 'missing_core_metadata')
            ->assertJsonPath('priorities.0.severity', 'warning')
            ->assertJsonPath('priorities.0.affected_fields.0.field', 'category')
            ->assertJsonPath('priorities.0.example_torrents.0.torrent_name', 'Weak Movie')
            ->assertJsonPath('priorities.0.recommended_staff_action', 'Review upload metadata mapping for category and type.')
            ->assertJsonPath('source_overview.summary.total_visible_torrents', 2);
    }

    public function test_authenticated_user_can_read_healthy_priority_state(): void
    {
        $category = Category::factory()->create(['name' => 'Movies']);
        $torrent = Torrent::factory()->create(['category_id' => $category->id, 'name' => 'Healthy Movie']);
        TorrentMetadata::query()->create($this->metadata($torrent, [
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'language' => 'english',
            'audio_language' => 'english',
            'subtitle_language' => 'spanish',
            'release_group' => 'NTB',
            'year' => 2026,
        ]));

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.operations-priorities'))
            ->assertOk()
            ->assertJsonPath('priorities.0.type', 'healthy_discovery_condition')
            ->assertJsonPath('priorities.0.severity', 'info')
            ->assertJsonPath('priorities.0.recommended_staff_action', 'No action required; discovery coverage is currently healthy.');
    }

    public function test_discovery_operations_priorities_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)->json($method, route('api.discovery.operations-priorities'))->assertStatus(405);
        }
    }

    private function metadata(Torrent $torrent, array $attributes): array
    {
        return array_merge(['torrent_id' => $torrent->id], $attributes);
    }
}
