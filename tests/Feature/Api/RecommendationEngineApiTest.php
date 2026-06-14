<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationEngineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_engine_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/engine', route('api.recommendations.engine', [], false));
    }

    public function test_recommendation_engine_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.engine'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_engine_foundation_contract(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDay(),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.engine'));

        $response->assertOk();
        $response->assertExactJson([
            'version' => 1,
            'engine' => 'metadata_recommendation_engine_foundation',
            'readonly' => true,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'metadata_categories' => [
                'sources',
                'resolutions',
                'languages',
                'release_groups',
            ],
            'signal_groups' => [
                'popular',
                'trending',
            ],
            'weights' => [
                'popular' => 60,
                'trending' => 40,
            ],
            'candidate_groups' => [
                [
                    'source' => 'WEB-DL',
                    'resolution' => '1080p',
                ],
            ],
            'signals' => [
                'popular' => [
                    'sources' => [
                        ['value' => 'WEB-DL', 'count' => 1],
                    ],
                    'resolutions' => [
                        ['value' => '1080p', 'count' => 1],
                    ],
                    'languages' => [
                        ['value' => 'english', 'count' => 1],
                    ],
                    'release_groups' => [
                        ['value' => 'NTB', 'count' => 1],
                    ],
                ],
                'trending' => [
                    'window' => '30d',
                    'sources' => [
                        ['value' => 'WEB-DL', 'count' => 1],
                    ],
                    'resolutions' => [
                        ['value' => '1080p', 'count' => 1],
                    ],
                    'release_groups' => [
                        ['value' => 'NTB', 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function test_recommendation_engine_endpoint_excludes_recommended_torrent_and_personalization_output(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.engine'));

        $response->assertOk();
        $payload = $response->json();
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ([
            'recommended_torrents',
            'torrent_id',
            'score',
            'rank',
            'personalized',
            'user_history',
            'download_history',
            'watch_history',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $payload);
            $this->assertStringNotContainsString('"'.$forbiddenKey.'"', $encodedPayload);
        }
    }

    public function test_recommendation_engine_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.engine'))
                ->assertStatus(405);
        }
    }

    /**
     * @param  array<string, string|null>  $metadata
     */
    private function createMetadata(Torrent $torrent, array $metadata): void
    {
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            ...$metadata,
        ]);
    }
}
