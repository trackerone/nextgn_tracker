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

    public function test_metadata_discovery_returns_empty_arrays_when_no_visible_metadata_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.metadata'));

        $response->assertOk();
        $response->assertExactJson([
            'sources' => [],
            'resolutions' => [],
            'languages' => [],
            'audio_languages' => [],
            'subtitle_languages' => [],
            'release_groups' => [],
        ]);
    }

    public function test_metadata_discovery_returns_the_expected_contract_shape_and_ordering(): void
    {
        $user = User::factory()->create();

        $primaryTorrent = Torrent::factory()->create();
        $secondaryTorrent = Torrent::factory()->create();
        $alphaTieTorrent = Torrent::factory()->create();
        $betaTieTorrent = Torrent::factory()->create();
        $emptyValueTorrent = Torrent::factory()->create();
        $nullValueTorrent = Torrent::factory()->create();
        $hiddenTorrent = Torrent::factory()->banned()->create();
        $unapprovedTorrent = Torrent::factory()->unapproved()->create();

        $this->createMetadata($primaryTorrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        $this->createMetadata($secondaryTorrent, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish',
            'release_group' => 'NTB',
        ]);

        $this->createMetadata($alphaTieTorrent, [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'language' => 'french',
            'audio_language' => 'english',
            'subtitle_language' => 'english',
            'release_group' => 'Alpha',
        ]);

        $this->createMetadata($betaTieTorrent, [
            'source' => 'HDTV',
            'resolution' => '720p',
            'language' => 'german',
            'audio_language' => 'french',
            'subtitle_language' => 'french',
            'release_group' => 'Beta',
        ]);

        $this->createMetadata($emptyValueTorrent, [
            'source' => '',
            'resolution' => '',
            'language' => '',
            'audio_language' => '',
            'subtitle_language' => '',
            'release_group' => '',
        ]);

        $this->createMetadata($nullValueTorrent, [
            'source' => null,
            'resolution' => null,
            'language' => null,
            'audio_language' => null,
            'subtitle_language' => null,
            'release_group' => null,
        ]);

        $this->createMetadata($hiddenTorrent, [
            'source' => 'CAM',
            'resolution' => '480p',
            'language' => 'italian',
            'audio_language' => 'italian',
            'subtitle_language' => 'italian',
            'release_group' => 'Hidden',
        ]);

        $this->createMetadata($unapprovedTorrent, [
            'source' => 'DVDRip',
            'resolution' => '576p',
            'language' => 'portuguese',
            'audio_language' => 'portuguese',
            'subtitle_language' => 'portuguese',
            'release_group' => 'Pending',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.discovery.metadata'));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame([
            'sources',
            'resolutions',
            'languages',
            'audio_languages',
            'subtitle_languages',
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
            'languages' => [
                ['value' => 'english', 'count' => 2],
                ['value' => 'french', 'count' => 1],
                ['value' => 'german', 'count' => 1],
            ],
            'audio_languages' => [
                ['value' => 'japanese', 'count' => 2],
                ['value' => 'english', 'count' => 1],
                ['value' => 'french', 'count' => 1],
            ],
            'subtitle_languages' => [
                ['value' => 'danish', 'count' => 2],
                ['value' => 'english', 'count' => 1],
                ['value' => 'french', 'count' => 1],
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

    public function test_metadata_discovery_endpoint_is_readonly(): void
    {
        $user = User::factory()->create();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->actingAs($user)
                ->json($method, route('api.discovery.metadata'))
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
