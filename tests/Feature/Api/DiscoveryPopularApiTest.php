<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DiscoveryPopularApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_access_popular_discovery_endpoint(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular'));

        $response->assertOk();
        $response->assertJsonPath('sources.0.value', 'WEB-DL');
        $response->assertJsonPath('sources.0.count', 1);
        $response->assertJsonPath('resolutions.0.value', '1080p');
        $response->assertJsonPath('resolutions.0.count', 1);
        $response->assertJsonPath('release_groups.0.value', 'NTB');
        $response->assertJsonPath('release_groups.0.count', 1);
    }

    public function test_popular_discovery_preserves_default_response_shape_when_category_is_omitted(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular'));

        $response->assertOk();
        $this->assertSame([
            'sources',
            'resolutions',
            'release_groups',
        ], array_keys($response->json()));
    }

    public function test_popular_discovery_can_return_only_sources_category(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular', ['category' => 'sources']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'WEB-DL', 'count' => 1],
            ],
        ]);
    }

    public function test_popular_discovery_can_return_only_resolutions_category(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular', ['category' => 'resolutions']));

        $response->assertOk();
        $response->assertExactJson([
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
        ]);
    }

    public function test_popular_discovery_can_return_only_release_groups_category(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular', ['category' => 'release_groups']));

        $response->assertOk();
        $response->assertExactJson([
            'release_groups' => [
                ['value' => 'NTB', 'count' => 1],
            ],
        ]);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.popular', ['category' => 'genres']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_popular_category_filtering_preserves_all_time_visible_behavior(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $oldVisibleTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subYears(10),
        ]);
        $hiddenTorrent = Torrent::factory()->banned()->create([
            'uploaded_at' => now()->subDays(1),
        ]);
        $unapprovedTorrent = Torrent::factory()->unapproved()->create([
            'uploaded_at' => now()->subDays(1),
        ]);

        $this->createMetadata($oldVisibleTorrent, [
            'source' => 'Old Visible Source',
            'resolution' => '2160p',
            'release_group' => 'OldGroup',
        ]);

        $this->createMetadata($hiddenTorrent, [
            'source' => 'Hidden Source',
            'resolution' => '720p',
            'release_group' => 'HiddenGroup',
        ]);

        $this->createMetadata($unapprovedTorrent, [
            'source' => 'Pending Source',
            'resolution' => '480p',
            'release_group' => 'PendingGroup',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.popular', ['category' => 'sources']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'Old Visible Source', 'count' => 1],
            ],
        ]);
    }

    public function test_popular_discovery_endpoint_is_readonly(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.popular'))
                ->assertStatus(405);
        }
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    private function createMetadata(Torrent $torrent, array $attributes): void
    {
        TorrentMetadata::query()->create(array_merge([
            'torrent_id' => $torrent->id,
        ], $attributes));
    }
}
