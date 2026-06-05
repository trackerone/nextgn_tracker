<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Tests\TestCase;

final class DiscoveryDashboardTest extends TestCase
{
    public function test_authenticated_user_can_access_discovery_dashboard(): void
    {
        $user = User::factory()->create();

        $this->get(route('my.discovery'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Discovery dashboard')
            ->assertSee('Latest uploads')
            ->assertSee('Popular releases')
            ->assertSee('Danish releases')
            ->assertSee('Nordic releases')
            ->assertSee('Releases with subtitles');
    }

    public function test_discovery_dashboard_renders_latest_uploads_with_metadata_badges(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Latest Upload Alpha 2026 1080p WEB-DL',
            'seeders' => 5,
            'completed' => 12,
        ]);
        $this->metadata($torrent, [
            'title' => 'Latest Upload Alpha',
            'year' => 2026,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'language' => 'en',
            'audio_language' => 'da',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Latest Upload Alpha')
            ->assertSee('Movie')
            ->assertSee('1080p')
            ->assertSee('WEB-DL')
            ->assertSee('2026')
            ->assertSee('Language: EN')
            ->assertSee('Audio: DA');
    }

    public function test_discovery_dashboard_renders_danish_releases(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Danish Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Danish Release',
            'type' => 'movie',
            'language' => 'Danish',
            'audio_language' => 'da',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Danish releases')
            ->assertSee('Danish Release')
            ->assertSee('Language: DANISH')
            ->assertSee('Audio: DA');
    }

    public function test_discovery_dashboard_renders_nordic_releases(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Swedish Nordic Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Swedish Nordic Release',
            'type' => 'tv',
            'language' => 'sv',
            'subtitle_language' => 'Norwegian',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Nordic releases')
            ->assertSee('Swedish Nordic Release')
            ->assertSee('Language: SV')
            ->assertSee('Subtitle language: NORWEGIAN');
    }

    public function test_discovery_dashboard_renders_subtitles_section(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Subtitle Rich Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Subtitle Rich Release',
            'type' => 'movie',
            'subtitle_language' => 'da',
            'subtitles' => 'da,no,sv',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Releases with subtitles')
            ->assertSee('Subtitle Rich Release')
            ->assertSee('Subtitle language: DA')
            ->assertSee('Subtitles: DA,NO,SV');
    }

    public function test_discovery_dashboard_renders_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('No latest uploads are visible yet.')
            ->assertSee('No popular releases are visible yet.')
            ->assertSee('No Danish releases are visible yet.')
            ->assertSee('No Nordic releases are visible yet.')
            ->assertSee('No releases with subtitle metadata are visible yet.');
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function metadata(Torrent $torrent, array $metadata): TorrentMetadata
    {
        return TorrentMetadata::query()->create(array_merge([
            'torrent_id' => $torrent->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $metadata));
    }
}
