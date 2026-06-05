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
            ->assertSee('Releases with language metadata')
            ->assertSee('Releases with audio metadata')
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
            'audio_language' => 'ja',
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
            ->assertSee('Audio: JA');
    }

    public function test_discovery_dashboard_renders_language_metadata_section(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Language Metadata Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Language Metadata Release',
            'type' => 'movie',
            'language' => 'English',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Releases with language metadata')
            ->assertSee('Language Metadata Release')
            ->assertSee('Language: ENGLISH');
    }

    public function test_discovery_dashboard_renders_audio_metadata_section(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Audio Metadata Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Audio Metadata Release',
            'type' => 'tv',
            'audio_language' => 'Japanese',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Releases with audio metadata')
            ->assertSee('Audio Metadata Release')
            ->assertSee('Audio: JAPANESE');
    }

    public function test_discovery_dashboard_renders_subtitles_section(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Subtitle Rich Release 2026']);
        $this->metadata($torrent, [
            'title' => 'Subtitle Rich Release',
            'type' => 'movie',
            'subtitle_language' => 'Spanish',
            'subtitles' => 'English, Spanish, German',
        ]);

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('Releases with subtitles')
            ->assertSee('Subtitle Rich Release')
            ->assertSee('Subtitle language: SPANISH')
            ->assertSee('Subtitles: ENGLISH,SPANISH,GERMAN');
    }

    public function test_discovery_dashboard_renders_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk()
            ->assertSee('No latest uploads are visible yet.')
            ->assertSee('No popular releases are visible yet.')
            ->assertSee('No releases with language metadata are visible yet.')
            ->assertSee('No releases with audio metadata are visible yet.')
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
