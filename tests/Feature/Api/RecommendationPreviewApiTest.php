<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_preview_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/preview', route('api.recommendations.preview', [], false));
    }

    public function test_recommendation_preview_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.preview'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_recommendation_preview_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.recommendations.preview'))
            ->assertOk()
            ->assertExactJson([
                'version' => 1,
                'readonly' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'preview_groups' => [],
            ]);
    }

    public function test_authenticated_user_can_read_populated_recommendation_preview_contract(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Example WEB-DL 1080p',
            'uploaded_at' => now()->subDay(),
        ]);

        $this->createMetadata($torrent, [
            'title' => 'Example Movie',
            'year' => 2026,
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.preview'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('preview_groups.0.group', [
                'source' => 'WEB-DL',
                'resolution' => '1080p',
                'language' => 'english',
            ])
            ->assertJsonPath('preview_groups.0.items.0.torrent.id', $torrent->id)
            ->assertJsonPath('preview_groups.0.items.0.torrent.name', 'Example WEB-DL 1080p')
            ->assertJsonPath('preview_groups.0.items.0.metadata.title', 'Example Movie')
            ->assertJsonPath('preview_groups.0.items.0.metadata.source', 'WEB-DL')
            ->assertJsonPath('preview_groups.0.items.0.metadata.resolution', '1080p')
            ->assertJsonPath('preview_groups.0.items.0.metadata.language', 'english')
            ->assertJsonPath('preview_groups.0.items.0.reasons', [
                ['field' => 'source', 'value' => 'WEB-DL'],
                ['field' => 'resolution', 'value' => '1080p'],
                ['field' => 'language', 'value' => 'english'],
                ['field' => 'release_group', 'value' => 'NTB'],
            ]);
    }

    public function test_recommendation_preview_endpoint_excludes_personalization_and_history_contracts(): void
    {
        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->getJson(route('api.recommendations.preview'))
            ->assertOk()
            ->json();
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ([
            'personalized_recommendations',
            'user_id',
            'user_history',
            'download_history',
            'watch_history',
            'recommendation_score',
            'rank',
        ] as $forbiddenKey) {
            $this->assertStringNotContainsString('"'.$forbiddenKey.'"', $encodedPayload);
        }
    }

    public function test_recommendation_preview_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.preview'))
                ->assertStatus(405);
        }
    }

    /**
     * @param  array<string, string|int|null>  $metadata
     */
    private function createMetadata(Torrent $torrent, array $metadata): void
    {
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            ...$metadata,
        ]);
    }
}
