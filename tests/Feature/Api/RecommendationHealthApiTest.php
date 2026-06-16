<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationHealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_health_route_generates_expected_path(): void
    {
        $this->assertSame(
            '/api/recommendations/health',
            route('api.recommendations.health', [], false),
        );
    }

    public function test_recommendation_health_requires_authentication(): void
    {
        $this->getJson(route('api.recommendations.health'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_empty_recommendation_health_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.recommendations.health'))
            ->assertOk()
            ->assertExactJson([
                'version' => 1,
                'readonly' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'pipeline' => ['signals', 'candidates', 'output', 'preview', 'torrents', 'health'],
                'metrics' => [
                    'signals_generated' => 0,
                    'candidates_generated' => 0,
                    'outputs_generated' => 0,
                    'torrent_recommendations_generated' => 0,
                    'empty_outputs' => 1,
                    'empty_recommendation_results' => 0,
                    'recommendation_match_rate' => 0.0,
                ],
                'metadata_coverage' => $this->emptyCoverageContract(),
                'indicators' => [
                    'has_signals' => false,
                    'has_candidates' => false,
                    'has_outputs' => false,
                    'has_torrent_matches' => false,
                    'metadata_first' => true,
                ],
            ]);
    }

    public function test_authenticated_user_can_read_populated_recommendation_health_contract(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Movies']);
        $torrent = Torrent::factory()->create([
            'category_id' => $category->id,
            'name' => 'Health WEB-DL 1080p',
            'seeders' => 50,
            'uploaded_at' => now()->subDay(),
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Health Movie',
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
            ->getJson(route('api.recommendations.health'));

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('readonly', true)
            ->assertJsonPath('personalized', false)
            ->assertJsonPath('uses_user_history', false)
            ->assertJsonPath('uses_download_history', false)
            ->assertJsonPath('uses_watch_history', false)
            ->assertJsonPath('pipeline', ['signals', 'candidates', 'output', 'preview', 'torrents', 'health'])
            ->assertJsonPath('metrics.candidates_generated', 1)
            ->assertJsonPath('metrics.outputs_generated', 1)
            ->assertJsonPath('metrics.torrent_recommendations_generated', 1)
            ->assertJsonPath('metrics.empty_outputs', 0)
            ->assertJsonPath('metrics.empty_recommendation_results', 0)
            ->assertJsonPath('metrics.recommendation_match_rate', 1)
            ->assertJsonPath('metadata_coverage.0', [
                'field' => 'category',
                'label' => 'Category',
                'total' => 1,
                'covered' => 1,
                'missing' => 0,
                'coverage_rate' => 1.0,
            ])
            ->assertJsonPath('metadata_coverage.8', [
                'field' => 'year',
                'label' => 'Year',
                'total' => 1,
                'covered' => 1,
                'missing' => 0,
                'coverage_rate' => 1.0,
            ])
            ->assertJsonPath('indicators.has_signals', true)
            ->assertJsonPath('indicators.has_candidates', true)
            ->assertJsonPath('indicators.has_outputs', true)
            ->assertJsonPath('indicators.has_torrent_matches', true)
            ->assertJsonPath('indicators.metadata_first', true);

        $this->assertGreaterThan(0, $response->json('metrics.signals_generated'));
    }

    public function test_recommendation_health_response_shape_remains_stable(): void
    {
        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->getJson(route('api.recommendations.health'))
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
            'metrics',
            'metadata_coverage',
            'indicators',
        ], array_keys($payload));

        $this->assertSame([
            'signals_generated',
            'candidates_generated',
            'outputs_generated',
            'torrent_recommendations_generated',
            'empty_outputs',
            'empty_recommendation_results',
            'recommendation_match_rate',
        ], array_keys($payload['metrics']));
    }

    public function test_recommendation_health_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.health'))
                ->assertStatus(405);
        }
    }

    private function emptyCoverageContract(): array
    {
        return array_map(
            static fn (array $field): array => [
                'field' => $field[0],
                'label' => $field[1],
                'total' => 0,
                'covered' => 0,
                'missing' => 0,
                'coverage_rate' => 0.0,
            ],
            [
                ['category', 'Category'],
                ['type', 'Type'],
                ['resolution', 'Resolution'],
                ['source', 'Source'],
                ['language', 'Language'],
                ['audio_language', 'Audio Language'],
                ['subtitle_language', 'Subtitle Language'],
                ['release_group', 'Release Group'],
                ['year', 'Year'],
            ],
        );
    }
}
