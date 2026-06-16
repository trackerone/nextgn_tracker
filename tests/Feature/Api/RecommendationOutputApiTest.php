<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationOutputApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_output_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/output', route('api.recommendations.output', [], false));
    }

    public function test_recommendation_output_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.output'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_metadata_recommendation_output_contract(): void
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
            ->getJson(route('api.recommendations.output'));

        $response->assertOk();
        $response->assertExactJson([
            'version' => 1,
            'readonly' => true,
            'recommendation_groups' => [
                [
                    'source' => 'WEB-DL',
                    'resolution' => '1080p',
                    'language' => 'english',
                ],
            ],
        ]);
    }

    public function test_recommendation_output_endpoint_excludes_torrent_and_personalization_output(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.output'));

        $response->assertOk();
        $payload = $response->json();
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ([
            'recommendations',
            'recommended_torrents',
            'recommended_torrent',
            'torrents',
            'torrent_id',
            'score',
            'rank',
            'personalized',
            'personalized_recommendations',
            'user_id',
            'user_history',
            'download_history',
            'watch_history',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $payload);
            $this->assertStringNotContainsString('"'.$forbiddenKey.'"', $encodedPayload);
        }
    }

    public function test_recommendation_output_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.output'))
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
