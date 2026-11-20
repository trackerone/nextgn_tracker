<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Models\UserStat;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TorrentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_see_details(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'name' => 'Detail Test',
            'type' => 'movie',
            'seeders' => 10,
            'leechers' => 2,
            'completed' => 5,
            'imdb_id' => 'tt1234567',
            'tmdb_id' => '7654321',
            'description' => "Line one\n<script>alert('x')</script>",
            'nfo_text' => 'Example nfo content',
        ]);

        $response = $this->actingAs($user)->get('/torrents/'.$torrent->getKey());

        $response->assertOk();
        $response->assertSee('Detail Test');
        $response->assertSee('movie');
        $response->assertSee((string) $torrent->formatted_size);
        $response->assertSee((string) $torrent->seeders);
        $response->assertSee((string) $torrent->leechers);
        $response->assertSee((string) $torrent->completed);
        $response->assertSee('tt1234567');
        $response->assertSee('7654321');
        $response->assertSee('&lt;script&gt;alert', false);
        $response->assertSee('Example nfo content');
        $this->assertMatchesRegularExpression('/<details\b[^>]*>\s*<summary[^>]*>NFO<\/summary>/', (string) $response->getContent());
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_nfo_payload_is_rendered_as_escaped_plain_text(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Unsafe NFO Test',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'nfo' => "<script>alert(1)</script>\n<img src=x onerror=alert(1)>",
        ]);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertIsString($content);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
        $this->assertStringContainsString('&lt;img', $content);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringNotContainsString('<img', $content);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
    }

    public function test_details_show_release_files_and_enriched_film_without_padding(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        TorrentExternalMetadata::factory()->for($torrent)->create([
            'media_type' => 'movie',
            'title' => 'Film title',
            'overview' => 'Film synopsis.',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'tmdb_id' => '187017',
            'providers_payload' => [
                'tmdb' => [
                    'credits' => ['cast' => [['name' => 'Actor Name', 'character' => 'Lead role', 'profile_path' => '/actor.jpg']]],
                    'videos' => ['results' => [['site' => 'YouTube', 'type' => 'Trailer', 'official' => true, 'key' => 'XqMKdHcs5kQ']]],
                ],
            ],
        ]);
        Storage::fake('torrents')->put($torrent->torrentStoragePath(), app(BencodeService::class)->encode([
            'info' => ['files' => [
                ['path' => ['Feature', 'film.mkv'], 'length' => 512],
                ['path' => ['.pad', '32'], 'length' => 32],
            ]],
        ]));

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk()
            ->assertSeeInOrder(['<h1', 'class="nextgn-release-poster"', 'aria-label="Torrent facts"', 'id="release-files"', 'id="film-heading"'], false)
            ->assertSee('id="release-files"', false)
            ->assertSee('Film synopsis.')
            ->assertSee('https://image.tmdb.org/t/p/w500/poster.jpg', false)
            ->assertSee('https://image.tmdb.org/t/p/w1280/backdrop.jpg', false)
            ->assertSee('nextgn-film-grid-with-trailer', false)
            ->assertSee('css/nextgn-release.css', false)
            ->assertSee('js/nextgn-release-documents.js', false)
            ->assertSee('aria-controls="release-files" aria-expanded="false"', false)
            ->assertSee('https://www.themoviedb.org/movie/187017', false)
            ->assertSee('Actor Name')
            ->assertSee('Lead role')
            ->assertSee('data-trailer-id="XqMKdHcs5kQ"', false)
            ->assertSee('Feature/film.mkv')
            ->assertDontSee('.pad/32');
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_details_page_shows_metadata_fallback_when_metadata_missing(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'name' => 'No Metadata Torrent',

            // REQUIRED (NOT NULL fields)
            'type' => '',
            'source' => '',
            'resolution' => '',

            // Remove all metadata signals
            'imdb_id' => null,
            'tmdb_id' => null,
            'nfo_text' => null,
            'nfo_storage_path' => null,
            'tags' => [],
            'codecs' => null,
            'description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('No Metadata Torrent')
            ->assertSee('Metadata is not available for this torrent yet.');
    }

    public function test_details_page_shows_missing_description_state(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Guided Detail',
            'description' => null,
        ]);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $response->assertSee('No description was provided.');
    }

    public function test_details_page_shows_release_facts(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Decision Summary Detail']);

        $response = $this->actingAs($user)->get(route('torrents.show', $torrent));

        $response->assertOk();
        $response->assertSeeText('Torrent facts');
        $response->assertSeeText('Seeders');
        $response->assertSeeText('Leechers');
        $response->assertSeeText('Snatches');
    }

    public function test_details_page_shows_eligibility_message_for_ratio_denial(): void
    {
        $user = User::factory()->create();

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 1024,
        ]);

        $torrent = Torrent::factory()->create([
            'is_freeleech' => false,
        ]);

        $this->actingAs($user)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('Download denied: your ratio is below the required threshold.');
    }

    public function test_details_page_shows_upgrade_banner_when_better_version_exists(): void
    {
        $user = User::factory()->create();

        $best = Torrent::factory()->create();
        $current = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $best->id,
            'title' => 'Upgrade Film',
            'year' => 2024,
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'bluray',
            'release_group' => 'BEST',
            'imdb_id' => 'tt1234567',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $current->id,
            'title' => 'Upgrade Film',
            'year' => 2024,
            'type' => 'movie',
            'resolution' => '720p',
            'source' => 'web',
            'release_group' => 'LOW',
            'imdb_id' => 'tt1234567',
        ]);

        $this->actingAs($user)
            ->get(route('torrents.show', $current))
            ->assertOk()
            ->assertSee('A better version already exists for this release family.')
            ->assertSee('View better version #'.$best->id);
    }

    public function test_metadata_quality_signals_are_visible_only_for_staff(): void
    {
        $normalUser = User::factory()->create();

        $staffUser = User::factory()->create([
            'role' => 'moderator',
            'is_staff' => true,
        ]);

        $torrent = Torrent::factory()->create([
            'name' => 'Broken Meta '.Str::random(5),
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Broken Meta',
            'type' => 'movie',
        ]);

        $this->actingAs($normalUser)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertDontSee('Metadata review needed');

        $this->actingAs($staffUser)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('Metadata review needed');
    }
}
