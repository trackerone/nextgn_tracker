<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentMetadataSurfaceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_web_detail_use_the_same_effective_metadata_contract(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'type' => 'movie',
            'source' => 'WEB',
            'resolution' => '1080p',
            'imdb_id' => 'tt6000006',
            'tmdb_id' => 6006,
            'nfo_text' => 'legacy nfo',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Surface Contract',
            'year' => 2026,
            'type' => 'tv',
            'source' => '',
            'resolution' => null,
            'release_group' => '',
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo' => null,
        ]);

        $expected = [
            'title' => 'Surface Contract',
            'year' => 2026,
            'type' => 'tv',
            'resolution' => null,
            'source' => '',
            'release_group' => '',
            'language' => null,
            'audio_language' => null,
            'subtitle_language' => null,
            'subtitles' => null,
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo' => null,
        ];

        $this->actingAs($user)
            ->getJson('/api/torrents/'.$torrent->id)
            ->assertOk()
            ->assertJsonPath('data.metadata', $expected);

        $webResponse = $this->actingAs($user)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/torrents/'.$torrent->id);

        $webResponse->assertOk();
        $this->assertStringContainsString('text/html', (string) $webResponse->headers->get('content-type'));
        $this->assertStringContainsString('<!DOCTYPE html>', (string) $webResponse->getContent());

        // response()->view() returns an HTML Response in this surface, not a test-visible View object.
        $webResponse->assertSee($torrent->name);
        $webResponse->assertSee('Tv');
        $webResponse->assertDontSee('tt6000006');
        $webResponse->assertDontSee('6006');
        $webResponse->assertDontSee('legacy nfo');
    }

    public function test_browse_uses_filter_first_shell_and_keeps_metadata_in_filters(): void
    {
        $staff = User::factory()->staff()->create();
        $member = User::factory()->create();

        $approved = Torrent::factory()->create([
            'status' => Torrent::STATUS_APPROVED,
            'type' => 'movie',
            'source' => 'WEB',
        ]);
        $pending = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'type' => 'movie',
            'source' => 'WEB',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $approved->id,
            'type' => 'tv',
            'source' => 'BLURAY',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $pending->id,
            'type' => 'tv',
            'source' => 'BLURAY',
            'raw_payload' => [
                'release_advice' => [
                    'upgrade_available' => true,
                    'best_version_torrent_id' => 9876,
                    'best_version_is_current_upload' => false,
                ],
            ],
        ]);

        $browseResponse = $this->actingAs($member)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/torrents');

        $browseResponse->assertOk();
        $this->assertStringContainsString('text/html', (string) $browseResponse->headers->get('content-type'));
        $this->assertStringContainsString('<!DOCTYPE html>', (string) $browseResponse->getContent());

        $browseResponse->assertSee('Filter first, then browse');
        $browseResponse->assertSee('Search');
        $browseResponse->assertSee('Core filters');
        $browseResponse->assertSee('Order by');
        $browseResponse->assertSee('Metadata filters');
        $browseResponse->assertSee('Saved views');
        $browseResponse->assertSee('RSS');
        $browseResponse->assertSee($approved->name);
        $browseResponse->assertDontSee('Tv');
        $browseResponse->assertDontSee('Best version torrent ID: 9876');
        $browseResponse->assertDontSee('A better version already exists.');

        $moderationResponse = $this->actingAs($staff)
            ->withHeaders(['Accept' => 'text/html'])
            ->get(route('staff.torrents.moderation.index'));

        $moderationResponse->assertOk();
        $this->assertStringContainsString('text/html', (string) $moderationResponse->headers->get('content-type'));
        $this->assertStringContainsString('<!DOCTYPE html>', (string) $moderationResponse->getContent());

        // Moderation surface is rendered HTML; verify pending row reflects effective metadata output.
        $moderationResponse->assertSeeInOrder([$pending->name, 'Tv']);
        $moderationResponse->assertSee('A better version already exists.');
        $moderationResponse->assertSee('Best version torrent ID: 9876');
    }
}
