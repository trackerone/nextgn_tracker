<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryMetadataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_metadata_discovery_endpoint(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.metadata'));

        $response->assertOk();
        $response->assertJsonPath('sources.0.value', 'WEB-DL');
        $response->assertJsonPath('sources.0.count', 1);
        $response->assertJsonPath('resolutions.0.value', '1080p');
        $response->assertJsonPath('resolutions.0.count', 1);
        $response->assertJsonPath('languages.0.value', 'english');
        $response->assertJsonPath('languages.0.count', 1);
        $response->assertJsonPath('audio_languages.0.value', 'japanese');
        $response->assertJsonPath('audio_languages.0.count', 1);
        $response->assertJsonPath('subtitle_languages.0.value', 'danish');
        $response->assertJsonPath('subtitle_languages.0.count', 1);
        $response->assertJsonPath('release_groups.0.value', 'NTB');
        $response->assertJsonPath('release_groups.0.count', 1);
    }

    public function test_unauthenticated_user_is_rejected_from_metadata_discovery_endpoint(): void
    {
        $this->getJson(route('api.discovery.metadata'))
            ->assertUnauthorized();
    }

    public function test_metadata_discovery_returns_grouped_counts_and_ignores_null_and_empty_values(): void
    {
        $user = User::factory()->create();

        $firstTorrent = Torrent::factory()->create();
        $secondTorrent = Torrent::factory()->create();
        $thirdTorrent = Torrent::factory()->create();
        $hiddenTorrent = Torrent::factory()->unapproved()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $firstTorrent->id,
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $secondTorrent->id,
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $thirdTorrent->id,
            'source' => '',
            'resolution' => null,
            'language' => 'spanish',
            'audio_language' => '',
            'subtitle_language' => null,
            'release_group' => 'GRP',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $hiddenTorrent->id,
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.metadata'));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [
                ['value' => 'WEB-DL', 'count' => 2],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 2],
            ],
            'languages' => [
                ['value' => 'english', 'count' => 2],
                ['value' => 'spanish', 'count' => 1],
            ],
            'audio_languages' => [
                ['value' => 'japanese', 'count' => 2],
            ],
            'subtitle_languages' => [
                ['value' => 'danish', 'count' => 2],
            ],
            'release_groups' => [
                ['value' => 'NTB', 'count' => 2],
                ['value' => 'GRP', 'count' => 1],
            ],
        ]);

        $this->assertIsArray($response->json('sources'));
        $this->assertSame(['value', 'count'], array_keys($response->json('sources.0')));
        $this->assertSame(['value', 'count'], array_keys($response->json('languages.0')));
    }
}
