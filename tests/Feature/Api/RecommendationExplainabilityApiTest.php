<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationExplainabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_explainability_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/explainability', route('api.recommendations.explainability', [], false));
    }

    public function test_recommendation_explainability_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.explainability'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_recommendation_explainability_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.recommendations.explainability'))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('pipeline', ['signals', 'candidates', 'output', 'preview', 'torrents', 'health', 'explainability'])
            ->assertJsonPath('explanations', []);
    }

    public function test_authenticated_user_can_read_populated_recommendation_explainability_contract(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Explainable WEB-DL 1080p',
            'seeders' => 42,
            'uploaded_at' => now()->subDay(),
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Explainable Movie',
            'year' => 2026,
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'english',
            'subtitle_language' => null,
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.explainability'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('explanations.0.title', 'WEB-DL · 1080p · english')
            ->assertJsonPath('explanations.0.output_summary.matched_torrent_count', 1)
            ->assertJsonPath('explanations.0.matched_torrents.0.torrent.id', $torrent->id)
            ->assertJsonPath('explanations.0.matched_torrents.0.metadata_matched', [
                ['field' => 'source', 'value' => 'WEB-DL'],
                ['field' => 'resolution', 'value' => '1080p'],
                ['field' => 'language', 'value' => 'english'],
            ])
            ->assertJsonPath('explanations.0.matched_torrents.0.metadata_missing.0.field', 'subtitle_language')
            ->assertJsonPath('explanations.0.matched_torrents.0.metadata_weak', [])
            ->assertJsonPath('explanations.0.matched_torrents.0.match_score', null)
            ->assertJsonPath('explanations.0.metadata_reasons.0.field', 'source')
            ->assertJsonPath('explanations.0.metadata_reasons.1.field', 'resolution')
            ->assertJsonPath('explanations.0.metadata_reasons.2.field', 'language');
    }

    public function test_recommendation_explainability_response_shape_remains_stable(): void
    {
        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->getJson(route('api.recommendations.explainability'))
            ->assertOk()
            ->json();

        $this->assertSame([
            'version',
            'readonly',
            'personalized',
            'uses_user_history',
            'uses_download_history',
            'uses_watch_history',
            'pipeline',
            'summaries',
            'explanations',
        ], array_keys($payload));
    }

    public function test_recommendation_explainability_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.explainability'))
                ->assertStatus(405);
        }
    }
}
