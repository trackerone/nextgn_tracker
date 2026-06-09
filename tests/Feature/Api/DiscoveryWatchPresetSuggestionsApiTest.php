<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\NotificationWatchPreset;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryWatchPresetSuggestionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_watch_preset_suggestions_endpoint(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions'));

        $response->assertOk();
        $response->assertExactJson([
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
        ]);
    }

    public function test_unauthenticated_user_is_rejected_from_watch_preset_suggestions_endpoint(): void
    {
        $this->getJson(route('api.discovery.watch-preset-suggestions'))
            ->assertUnauthorized();
    }

    public function test_watch_preset_suggestions_return_only_watch_supported_categories_by_default(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'language' => 'japanese',
            'audio_language' => 'english',
            'subtitle_language' => 'danish',
            'release_group' => 'GroupA',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions'));

        $response->assertOk();

        $this->assertSame([
            'sources',
            'resolutions',
            'languages',
            'release_groups',
        ], array_keys($response->json()));
    }

    public function test_watch_preset_suggestions_can_return_each_supported_category(): void
    {
        $user = User::factory()->create();

        foreach ($this->supportedCategories() as $category => $field) {
            $torrent = Torrent::factory()->create();

            $this->createMetadata($torrent, [
                $field => $category.'-value',
            ]);

            $response = $this->actingAs($user)
                ->getJson(route('api.discovery.watch-preset-suggestions', ['category' => $category]));

            $response->assertOk();
            $response->assertExactJson([
                $category => [
                    ['value' => $category.'-value', 'count' => 1],
                ],
            ]);
        }
    }

    public function test_watch_preset_suggestions_trim_category_before_validation(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'HDTV',
        ]);

        $this->actingAs($user)
            ->getJson('/api/discovery/watch-preset-suggestions?category=%20sources%20')
            ->assertOk()
            ->assertExactJson([
                'sources' => [
                    ['value' => 'HDTV', 'count' => 1],
                ],
            ]);
    }

    public function test_invalid_watch_preset_suggestions_category_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions', ['category' => 'audio_languages']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_watch_preset_suggestions_return_empty_arrays_when_no_visible_metadata_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions'))
            ->assertOk()
            ->assertExactJson([
                'sources' => [],
                'resolutions' => [],
                'languages' => [],
                'release_groups' => [],
            ]);
    }

    public function test_watch_preset_suggestions_visibility_filtering_remains_delegated_to_discovery_metadata_service(): void
    {
        $user = User::factory()->create();
        $visibleTorrent = Torrent::factory()->create();
        $hiddenTorrent = Torrent::factory()->banned()->create();
        $unapprovedTorrent = Torrent::factory()->unapproved()->create();

        $this->createMetadata($visibleTorrent, [
            'source' => 'Visible Source',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'VisibleGroup',
        ]);

        $this->createMetadata($hiddenTorrent, [
            'source' => 'Hidden Source',
            'resolution' => '720p',
            'language' => 'french',
            'release_group' => 'HiddenGroup',
        ]);

        $this->createMetadata($unapprovedTorrent, [
            'source' => 'Pending Source',
            'resolution' => '480p',
            'language' => 'german',
            'release_group' => 'PendingGroup',
        ]);

        $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions'))
            ->assertOk()
            ->assertExactJson([
                'sources' => [
                    ['value' => 'Visible Source', 'count' => 1],
                ],
                'resolutions' => [
                    ['value' => '1080p', 'count' => 1],
                ],
                'languages' => [
                    ['value' => 'english', 'count' => 1],
                ],
                'release_groups' => [
                    ['value' => 'VisibleGroup', 'count' => 1],
                ],
            ]);
    }

    public function test_watch_preset_suggestions_endpoint_is_readonly(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.watch-preset-suggestions'))
                ->assertStatus(405);
        }
    }

    public function test_watch_preset_suggestions_do_not_mutate_watch_presets_or_notifications(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $preset = NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing preset',
            'filters' => ['source' => 'WEB-DL'],
            'is_enabled' => true,
        ]);

        TorrentWatchNotification::factory()->create([
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'notification_watch_preset_id' => $preset->id,
        ]);

        $this->createMetadata($torrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);

        $this->actingAs($user)
            ->getJson(route('api.discovery.watch-preset-suggestions'))
            ->assertOk();

        $this->assertDatabaseCount('notification_watch_presets', 1);
        $this->assertDatabaseCount('torrent_watch_notifications', 1);

        $preset->refresh();

        $this->assertSame('Existing preset', $preset->name);
        $this->assertSame(['source' => 'WEB-DL'], $preset->filters);
        $this->assertTrue($preset->is_enabled);
    }

    /**
     * @return array<string, string>
     */
    private function supportedCategories(): array
    {
        return [
            'sources' => 'source',
            'resolutions' => 'resolution',
            'languages' => 'language',
            'release_groups' => 'release_group',
        ];
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
