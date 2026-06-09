<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecommendationSignalsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_signals_route_generates_expected_path(): void
    {
        $this->assertSame('/api/recommendations/signals', route('api.recommendations.signals', [], false));
    }

    public function test_recommendation_signals_require_authentication(): void
    {
        $this->getJson(route('api.recommendations.signals'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_metadata_recommendation_signals_contract(): void
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
            ->getJson(route('api.recommendations.signals'));

        $response->assertOk();
        $response->assertExactJson([
            'version' => 1,
            'engine' => 'metadata_signals_foundation',
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
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

        $payload = $response->json();

        $this->assertArrayNotHasKey('torrents', $payload);
        $this->assertArrayNotHasKey('recommendations', $payload);
        $this->assertArrayNotHasKey('recommended_torrents', $payload);
    }

    public function test_recommendation_signals_keep_visibility_filtering_delegated_to_shared_services(): void
    {
        $user = User::factory()->create();

        $visibleTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDay(),
        ]);
        $hiddenTorrent = Torrent::factory()->banned()->create([
            'uploaded_at' => now()->subDay(),
        ]);
        $unapprovedTorrent = Torrent::factory()->unapproved()->create([
            'uploaded_at' => now()->subDay(),
        ]);
        $oldTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(31),
        ]);

        $this->createMetadata($visibleTorrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata($hiddenTorrent, [
            'source' => 'CAM',
            'resolution' => '480p',
            'language' => 'italian',
            'release_group' => 'Hidden',
        ]);
        $this->createMetadata($unapprovedTorrent, [
            'source' => 'DVDRip',
            'resolution' => '576p',
            'language' => 'portuguese',
            'release_group' => 'Pending',
        ]);
        $this->createMetadata($oldTorrent, [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'language' => 'french',
            'release_group' => 'Archive',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.recommendations.signals'));

        $response->assertOk();
        $response->assertJsonPath('signals.popular.sources', [
            ['value' => 'BluRay', 'count' => 1],
            ['value' => 'WEB-DL', 'count' => 1],
        ]);
        $response->assertJsonPath('signals.popular.languages', [
            ['value' => 'english', 'count' => 1],
            ['value' => 'french', 'count' => 1],
        ]);
        $response->assertJsonPath('signals.trending.sources', [
            ['value' => 'WEB-DL', 'count' => 1],
        ]);
        $response->assertJsonMissing(['value' => 'CAM']);
        $response->assertJsonMissing(['value' => 'DVDRip']);
        $response->assertJsonMissing(['value' => 'Pending']);
    }

    public function test_recommendation_signals_endpoint_is_get_only(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.recommendations.signals'))
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
