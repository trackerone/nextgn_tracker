<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $matches = [];
        $this->assertSame(
            1,
            preg_match('/<h2 class="text-lg font-semibold text-white">NFO<\/h2>\s*<pre[^>]*>(.*?)<\/pre>/s', $content, $matches)
        );

        $nfoMarkup = (string) ($matches[1] ?? '');

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $nfoMarkup);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $nfoMarkup);
        $this->assertStringNotContainsString('<script', $nfoMarkup);
        $this->assertStringNotContainsString('<img', $nfoMarkup);
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
