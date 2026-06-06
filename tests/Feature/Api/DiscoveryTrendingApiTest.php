<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DiscoveryTrendingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_access_trending_discovery_endpoint(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(3),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending'));

        $response->assertOk();
        $response->assertJsonPath('sources.0.value', 'WEB-DL');
        $response->assertJsonPath('sources.0.count', 1);
        $response->assertJsonPath('resolutions.0.value', '1080p');
        $response->assertJsonPath('resolutions.0.count', 1);
        $response->assertJsonPath('release_groups.0.value', 'NTB');
        $response->assertJsonPath('release_groups.0.count', 1);
    }

    public function test_unauthenticated_user_is_rejected_from_trending_discovery_endpoint(): void
    {
        $this->getJson(route('api.discovery.trending'))
            ->assertUnauthorized();
    }

    public function test_trending_discovery_defaults_to_a_30_day_window(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $includedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(30),
        ]);
        $excludedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(31),
        ]);

        $this->createMetadata($includedTorrent, [
            'source' => 'Within Default Window',
            'resolution' => '1080p',
            'release_group' => 'DefaultWindow',
        ]);

        $this->createMetadata($excludedTorrent, [
            'source' => 'Outside Default Window',
            'resolution' => '720p',
            'release_group' => 'OutsideWindow',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending'));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'Within Default Window', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'DefaultWindow', 'count' => 1],
            ],
        ]);
    }

    public function test_trending_discovery_supports_a_7_day_window(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $includedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(7),
        ]);
        $excludedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(8),
        ]);

        $this->createMetadata($includedTorrent, [
            'source' => 'Within Seven Days',
            'resolution' => '1080p',
            'release_group' => 'SevenDayGroup',
        ]);

        $this->createMetadata($excludedTorrent, [
            'source' => 'Outside Seven Days',
            'resolution' => '720p',
            'release_group' => 'OutsideSevenDayGroup',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['window' => '7d']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'Within Seven Days', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'SevenDayGroup', 'count' => 1],
            ],
        ]);
    }

    public function test_trending_discovery_supports_a_90_day_window(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $includedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(90),
        ]);
        $excludedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(91),
        ]);

        $this->createMetadata($includedTorrent, [
            'source' => 'Within Ninety Days',
            'resolution' => '1080p',
            'release_group' => 'NinetyDayGroup',
        ]);

        $this->createMetadata($excludedTorrent, [
            'source' => 'Outside Ninety Days',
            'resolution' => '720p',
            'release_group' => 'OutsideNinetyDayGroup',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['window' => '90d']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'Within Ninety Days', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'NinetyDayGroup', 'count' => 1],
            ],
        ]);
    }

    public function test_invalid_window_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['window' => '14d']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['window']);
    }

    public function test_trending_discovery_preserves_default_response_shape_when_category_is_omitted(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(1),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending'));

        $response->assertOk();
        $this->assertSame([
            'sources',
            'resolutions',
            'release_groups',
        ], array_keys($response->json()));
    }

    public function test_trending_discovery_can_return_only_sources_category(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(1),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['category' => 'sources']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'WEB-DL', 'count' => 1],
            ],
        ]);
    }

    public function test_trending_discovery_can_return_only_resolutions_category(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(1),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['category' => 'resolutions']));

        $response->assertOk();
        $response->assertExactJson([
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
        ]);
    }

    public function test_trending_discovery_can_return_only_release_groups_category(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(1),
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['category' => 'release_groups']));

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
            ->getJson(route('api.discovery.trending', ['category' => 'genres']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_trending_discovery_applies_window_when_filtering_by_category(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $includedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(7),
        ]);
        $excludedTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(8),
        ]);

        $this->createMetadata($includedTorrent, [
            'source' => 'Within Seven Days',
        ]);

        $this->createMetadata($excludedTorrent, [
            'source' => 'Outside Seven Days',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', [
                'window' => '7d',
                'category' => 'sources',
            ]));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'Within Seven Days', 'count' => 1],
            ],
        ]);
    }

    public function test_trending_discovery_returns_recent_visible_metadata_only_with_expected_ordering(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        $primaryTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(1),
        ]);
        $secondaryTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(2),
        ]);
        $alphaTieTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(3),
        ]);
        $betaTieTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(4),
        ]);
        $emptyValueTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(5),
        ]);
        $nullValueTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(6),
        ]);
        $oldTorrent = Torrent::factory()->create([
            'uploaded_at' => now()->subDays(31),
        ]);
        $hiddenTorrent = Torrent::factory()->banned()->create([
            'uploaded_at' => now()->subDays(2),
        ]);
        $unapprovedTorrent = Torrent::factory()->unapproved()->create([
            'uploaded_at' => now()->subDays(2),
        ]);

        $this->createMetadata($primaryTorrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $this->createMetadata($secondaryTorrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'release_group' => 'NTB',
        ]);

        $this->createMetadata($alphaTieTorrent, [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'release_group' => 'Alpha',
        ]);

        $this->createMetadata($betaTieTorrent, [
            'source' => 'HDTV',
            'resolution' => '720p',
            'release_group' => 'Beta',
        ]);

        $this->createMetadata($emptyValueTorrent, [
            'source' => '',
            'resolution' => '',
            'release_group' => '',
        ]);

        $this->createMetadata($nullValueTorrent, [
            'source' => null,
            'resolution' => null,
            'release_group' => null,
        ]);

        $this->createMetadata($oldTorrent, [
            'source' => 'Old Source',
            'resolution' => '480p',
            'release_group' => 'OldGroup',
        ]);

        $this->createMetadata($hiddenTorrent, [
            'source' => 'CAM',
            'resolution' => '576p',
            'release_group' => 'Hidden',
        ]);

        $this->createMetadata($unapprovedTorrent, [
            'source' => 'DVDRip',
            'resolution' => '540p',
            'release_group' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending'));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame([
            'sources',
            'resolutions',
            'release_groups',
        ], array_keys($payload));

        $response->assertExactJson([
            'sources' => [
                ['value' => 'WEB-DL', 'count' => 2],
                ['value' => 'BluRay', 'count' => 1],
                ['value' => 'HDTV', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 2],
                ['value' => '2160p', 'count' => 1],
                ['value' => '720p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'NTB', 'count' => 2],
                ['value' => 'Alpha', 'count' => 1],
                ['value' => 'Beta', 'count' => 1],
            ],
        ]);

        foreach ($payload as $entries) {
            $this->assertIsArray($entries);

            foreach ($entries as $entry) {
                $this->assertIsArray($entry);
                $this->assertSame(['value', 'count'], array_keys($entry));
                $this->assertIsInt($entry['count']);
            }
        }
    }

    public function test_trending_discovery_limits_sources_to_the_top_25_entries(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        foreach (range(1, 3) as $iteration) {
            $this->createMetadata(Torrent::factory()->create([
                'uploaded_at' => now()->subDays(1),
            ]), [
                'source' => 'Top Alpha',
            ]);
        }

        foreach (range(1, 2) as $iteration) {
            $this->createMetadata(Torrent::factory()->create([
                'uploaded_at' => now()->subDays(1),
            ]), [
                'source' => 'Top Beta',
            ]);
        }

        foreach (range(1, 26) as $index) {
            $this->createMetadata(Torrent::factory()->create([
                'uploaded_at' => now()->subDays(1),
            ]), [
                'source' => sprintf('Source %02d', $index),
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['window' => '90d']));

        $response->assertOk();

        $expectedSources = [
            ['value' => 'Top Alpha', 'count' => 3],
            ['value' => 'Top Beta', 'count' => 2],
        ];

        foreach (range(1, 23) as $index) {
            $expectedSources[] = [
                'value' => sprintf('Source %02d', $index),
                'count' => 1,
            ];
        }

        $response->assertJsonCount(25, 'sources');
        $response->assertExactJson([
            'sources' => $expectedSources,
            'resolutions' => [],
            'release_groups' => [],
        ]);
    }

    public function test_trending_discovery_returns_empty_arrays_when_no_recent_visible_metadata_exists(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $user = User::factory()->create();

        Torrent::factory()->create([
            'uploaded_at' => now()->subDays(31),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.trending', ['window' => '7d']));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [],
            'resolutions' => [],
            'release_groups' => [],
        ]);
    }

    public function test_trending_discovery_endpoint_is_readonly(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.trending'))
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
