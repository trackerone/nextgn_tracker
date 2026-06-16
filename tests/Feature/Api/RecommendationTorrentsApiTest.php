<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationTorrentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_torrents_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/torrents', route('api.recommendations.torrents', [], false));
    }

    public function test_recommendation_torrents_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.torrents'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_recommendation_torrents_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.recommendations.torrents'))
            ->assertOk()
            ->assertExactJson([
                'version' => 1,
                'readonly' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'pipeline' => ['signals', 'candidates', 'output', 'preview', 'torrents'],
                'recommendations' => [],
            ]);
    }

    public function test_authenticated_user_can_read_populated_recommendation_torrents_contract(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Concrete WEB-DL 1080p',
            'seeders' => 42,
            'uploaded_at' => now()->subDay(),
        ]);

        $this->createMetadata($torrent, [
            'title' => 'Concrete Movie',
            'year' => 2026,
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'english',
            'subtitle_language' => 'spanish',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.torrents'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('pipeline', ['signals', 'candidates', 'output', 'preview', 'torrents'])
            ->assertJsonPath('recommendations.0.recommendation.title', 'WEB-DL · 1080p · english')
            ->assertJsonPath('recommendations.0.recommendation.explanation', 'Metadata recommendation output resolved against visible torrents with matching taxonomy fields.')
            ->assertJsonPath('recommendations.0.recommendation.metadata', [
                'source' => 'WEB-DL',
                'resolution' => '1080p',
                'language' => 'english',
            ])
            ->assertJsonPath('recommendations.0.torrents.0.torrent.id', $torrent->id)
            ->assertJsonPath('recommendations.0.torrents.0.torrent.name', 'Concrete WEB-DL 1080p')
            ->assertJsonPath('recommendations.0.torrents.0.metadata.title', 'Concrete Movie')
            ->assertJsonPath('recommendations.0.torrents.0.metadata.source', 'WEB-DL')
            ->assertJsonPath('recommendations.0.torrents.0.metadata.resolution', '1080p')
            ->assertJsonPath('recommendations.0.torrents.0.metadata.language', 'english')
            ->assertJsonPath('recommendations.0.torrents.0.matched_fields', [
                ['field' => 'source', 'value' => 'WEB-DL'],
                ['field' => 'resolution', 'value' => '1080p'],
                ['field' => 'language', 'value' => 'english'],
            ]);

        $this->assertIsString($response->json('recommendations.0.recommendation.identifier'));
        $this->assertStringContainsString('source, resolution, language', (string) $response->json('recommendations.0.torrents.0.match_reason'));
    }

    public function test_recommendation_torrents_endpoint_excludes_personalization_and_history_contracts(): void
    {
        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->getJson(route('api.recommendations.torrents'))
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

    public function test_recommendation_torrents_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.torrents'))
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
