<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Models\UserStat;
use Tests\TestCase;

final class RssFeedTest extends TestCase
{
    public function test_invalid_token_is_rejected(): void
    {
        $this->get('/rss/not-a-real-token')->assertNotFound();
    }

    public function test_valid_token_returns_rss_xml(): void
    {
        $user = $this->rssUser();
        $torrent = Torrent::factory()->create(['name' => 'Matrix Reloaded 2160p']);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'The Matrix Reloaded',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'bluray',
            'release_group' => 'NTB',
            'year' => 2003,
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee('<rss version="2.0">', false);
        $response->assertSee('The Matrix Reloaded');
        $response->assertSee('Resolution: 2160p');
    }

    public function test_feed_includes_only_visible_and_eligible_torrents(): void
    {
        $user = $this->rssUser(uploadedBytes: 1, downloadedBytes: 10);
        $eligible = Torrent::factory()->create(['name' => 'Eligible Freeleech', 'is_freeleech' => true, 'freeleech' => true]);
        $ratioBlocked = Torrent::factory()->create(['name' => 'Ratio Blocked']);
        $hidden = Torrent::factory()->unapproved()->create(['name' => 'Hidden Pending']);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token]));

        $response->assertOk();
        $response->assertSee($eligible->name);
        $response->assertDontSee($ratioBlocked->name);
        $response->assertDontSee($hidden->name);
    }

    public function test_filters_work_for_supported_metadata_and_torrent_fields(): void
    {
        $user = $this->rssUser();
        $category = Category::factory()->create();
        $match = Torrent::factory()->create([
            'name' => 'Matrix Matched Release',
            'category_id' => $category->id,
            'is_freeleech' => true,
            'freeleech' => true,
        ]);
        $miss = Torrent::factory()->create([
            'name' => 'Matrix Other Release',
            'category_id' => $category->id,
            'is_freeleech' => true,
            'freeleech' => true,
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Matrix Matched Release',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'bluray',
            'release_group' => 'NTB',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'Matrix Other Release',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'web',
            'release_group' => 'FLUX',
        ]);

        $response = $this->get(route('rss.feed', [
            'token' => $user->rss_token,
            'q' => 'matrix',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'bluray',
            'release_group' => 'NTB',
            'freeleech' => '1',
            'category' => (string) $category->id,
        ]));

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($miss->name);
    }

    public function test_limit_is_capped_to_one_hundred_items(): void
    {
        $user = $this->rssUser();
        Torrent::factory()->count(105)->sequence(fn ($sequence): array => [
            'name' => 'Limit Torrent '.$sequence->index,
            'uploaded_at' => now()->subSeconds($sequence->index),
        ])->create();

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'limit' => '1000']));

        $response->assertOk();
        self::assertSame(100, substr_count($response->getContent(), '<item>'));
    }

    public function test_metadata_output_uses_torrent_metadata_view_behavior(): void
    {
        $user = $this->rssUser();
        $torrent = Torrent::factory()->create([
            'name' => 'Legacy Title',
            'type' => 'movie',
            'resolution' => '720p',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Normalized Title',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'web',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token]));

        $response->assertOk();
        $response->assertSee('Normalized Title');
        $response->assertSee('Type: tv');
        $response->assertSee('Resolution: 1080p');
        $response->assertDontSee('Resolution: 720p');
    }

    public function test_rotating_rss_token_invalidates_old_feed_url(): void
    {
        $user = $this->rssUser();
        $oldToken = (string) $user->rss_token;

        $this->actingAs($user)
            ->post(route('account.rss.rotate'))
            ->assertRedirect(route('account.rss.index'));

        $user->refresh();

        self::assertNotSame($oldToken, $user->rss_token);
        $this->get(route('rss.feed', ['token' => $oldToken]))->assertNotFound();
        $this->get(route('rss.feed', ['token' => $user->rss_token]))->assertOk();
    }

    private function rssUser(int $uploadedBytes = 1_000_000, int $downloadedBytes = 1): User
    {
        $user = User::factory()->create();
        $user->rotateRssToken();

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => $uploadedBytes,
            'downloaded_bytes' => $downloadedBytes,
        ]);

        return $user->refresh();
    }
}
