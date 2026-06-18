<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryExplainabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_explainability_route_generates_expected_path(): void
    {
        $this->assertSame(
            '/api/discovery/explainability',
            route('api.discovery.explainability', [], false),
        );
    }

    public function test_discovery_explainability_requires_authentication(): void
    {
        $this->getJson(route('api.discovery.explainability'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_explainability_contract(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.explainability'))
            ->assertOk()
            ->assertExactJson([
                'version' => 1,
                'readonly' => true,
                'metadata_first' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'explanations' => [],
            ]);
    }

    public function test_authenticated_user_can_read_populated_explainability_states(): void
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

        $weak = Torrent::factory()->create([
            'category_id' => $category->id,
            'name' => 'Weak Movie',
        ]);
        TorrentMetadata::query()->create($this->metadata($weak, ['type' => 'movie', 'resolution' => '720p']));

        $missingCore = Torrent::factory()->create([
            'category_id' => null,
            'name' => 'Missing Movie',
            'type' => 'movie',
        ]);
        TorrentMetadata::query()->create($this->metadata($missingCore, ['type' => 'movie', 'resolution' => '720p']));

        Torrent::factory()->banned()->create([
            'category_id' => $category->id,
            'name' => 'Hidden Banned',
        ]);
        Torrent::factory()->unapproved()->create([
            'category_id' => $category->id,
            'name' => 'Hidden Pending',
        ]);

        $response = $this->actingAs($user)->getJson(route('api.discovery.explainability'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('metadata_first', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonCount(3, 'explanations');

        $payload = $response->json();
        $byName = collect($payload['explanations'])->keyBy('torrent_name');

        $this->assertSame('discovery_ready', $byName['Ready Movie']['discovery_status']);
        $this->assertSame([], $byName['Ready Movie']['metadata_missing']);
        $this->assertSame([], $byName['Ready Movie']['metadata_weak']);
        $this->assertSame('weakly_discoverable', $byName['Weak Movie']['discovery_status']);
        $this->assertNotEmpty($byName['Weak Movie']['metadata_missing']);
        $this->assertNotEmpty($byName['Weak Movie']['metadata_weak']);
        $this->assertSame('missing_core_metadata', $byName['Missing Movie']['discovery_status']);
        $this->assertSame('category', $byName['Missing Movie']['metadata_missing'][0]['field']);
        $this->assertStringContainsString('Discovery Ready', $byName['Ready Movie']['discovery_summary']);
        $this->assertStringContainsString('Weakly Discoverable', $byName['Weak Movie']['discovery_summary']);
        $this->assertStringContainsString('Missing Core Metadata', $byName['Missing Movie']['discovery_summary']);
    }

    public function test_discovery_explainability_response_shape_remains_stable(): void
    {
        $payload = $this->actingAs(User::factory()->create())
            ->getJson(route('api.discovery.explainability'))
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
            'explanations',
        ], array_keys($payload));
    }

    public function test_discovery_explainability_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.explainability'))
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
