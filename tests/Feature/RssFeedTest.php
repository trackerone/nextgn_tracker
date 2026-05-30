<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Models\UserStat;
use App\Services\BencodeService;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Support\Facades\Storage;
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

    public function test_audio_language_filter_matches_danish_metadata(): void
    {
        $user = $this->rssUser();
        $match = Torrent::factory()->create(['name' => 'Danish Audio Movie']);
        $miss = Torrent::factory()->create(['name' => 'English Audio Movie']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Danish Audio Movie',
            'audio_language' => 'da',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'English Audio Movie',
            'audio_language' => 'en',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'audio_language' => 'da']));

        $response->assertOk();
        $response->assertSee('Danish Audio Movie');
        $response->assertSee('Audio language: da');
        $response->assertDontSee('English Audio Movie');
    }

    public function test_subtitle_language_filter_matches_danish_metadata(): void
    {
        $user = $this->rssUser();
        $match = Torrent::factory()->create(['name' => 'Danish Subtitle Movie']);
        $miss = Torrent::factory()->create(['name' => 'Norwegian Subtitle Movie']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Danish Subtitle Movie',
            'subtitle_language' => 'da',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'Norwegian Subtitle Movie',
            'subtitle_language' => 'no',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'subtitle_language' => 'da']));

        $response->assertOk();
        $response->assertSee('Danish Subtitle Movie');
        $response->assertSee('Subtitle language: da');
        $response->assertDontSee('Norwegian Subtitle Movie');
    }

    public function test_subtitles_filter_accepts_comma_separated_languages(): void
    {
        $user = $this->rssUser();
        $norwegian = Torrent::factory()->create(['name' => 'Nordic Norwegian Subs']);
        $swedish = Torrent::factory()->create(['name' => 'Nordic Swedish Subs']);
        $english = Torrent::factory()->create(['name' => 'English Subs']);

        TorrentMetadata::query()->create([
            'torrent_id' => $norwegian->id,
            'title' => 'Nordic Norwegian Subs',
            'subtitles' => 'no',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $swedish->id,
            'title' => 'Nordic Swedish Subs',
            'subtitles' => 'sv',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $english->id,
            'title' => 'English Subs',
            'subtitles' => 'en',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'subtitles' => 'da,no,sv']));

        $response->assertOk();
        $response->assertSee('Nordic Norwegian Subs');
        $response->assertSee('Nordic Swedish Subs');
        $response->assertSee('Subtitles: no');
        $response->assertDontSee('English Subs');
    }

    public function test_language_filters_match_case_insensitive_names(): void
    {
        $user = $this->rssUser();
        $match = Torrent::factory()->create(['name' => 'Dansk Language Movie']);
        $miss = Torrent::factory()->create(['name' => 'Finnish Language Movie']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Dansk Language Movie',
            'language' => 'Dansk',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'Finnish Language Movie',
            'language' => 'Finnish',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'language' => 'DANISH']));

        $response->assertOk();
        $response->assertSee('Dansk Language Movie');
        $response->assertSee('Language: Dansk');
        $response->assertDontSee('Finnish Language Movie');
    }

    public function test_language_filters_still_respect_torrent_eligibility(): void
    {
        $user = $this->rssUser(uploadedBytes: 1, downloadedBytes: 10);
        $eligible = Torrent::factory()->create([
            'name' => 'Eligible Danish Freeleech',
            'is_freeleech' => true,
            'freeleech' => true,
        ]);
        $blocked = Torrent::factory()->create(['name' => 'Blocked Danish Ratio']);

        TorrentMetadata::query()->create([
            'torrent_id' => $eligible->id,
            'title' => 'Eligible Danish Freeleech',
            'language' => 'da',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $blocked->id,
            'title' => 'Blocked Danish Ratio',
            'language' => 'da',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'language' => 'da']));

        $response->assertOk();
        $response->assertSee('Eligible Danish Freeleech');
        $response->assertDontSee('Blocked Danish Ratio');
    }

    public function test_rss_download_rejects_invalid_and_rotated_tokens(): void
    {
        Storage::fake('torrents');

        $user = $this->rssUser();
        $torrent = Torrent::factory()->create();
        $this->storeTorrentPayload($torrent);
        $oldToken = (string) $user->rss_token;

        $this->get(route('rss.torrents.download', [
            'token' => 'notarealtoken',
            'torrent' => $torrent->id,
        ]))->assertNotFound();

        $user->rotateRssToken();

        $this->get(route('rss.torrents.download', [
            'token' => $oldToken,
            'torrent' => $torrent->id,
        ]))->assertNotFound();
    }

    public function test_rss_download_returns_personalized_torrent_for_eligible_torrent(): void
    {
        Storage::fake('torrents');
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');

        $user = $this->rssUser();
        $torrent = Torrent::factory()->create(['slug' => 'rss-safe-download']);
        $this->storeTorrentPayload($torrent);

        $response = $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $torrent->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-bittorrent');
        $response->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        self::assertStringContainsString(
            'rss-safe-download.torrent',
            (string) $response->headers->get('Content-Disposition')
        );

        $decoded = $this->decodeTorrentPayload($response->streamedContent());

        self::assertSame(sprintf('https://tracker.example/announce/%s', $user->passkey), $decoded['announce'] ?? null);
    }

    public function test_rss_download_rejects_hidden_unapproved_and_ratio_blocked_torrents(): void
    {
        Storage::fake('torrents');
        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
        ]);

        $user = $this->rssUser(uploadedBytes: 100, downloadedBytes: 1_000);
        $hidden = Torrent::factory()->create(['is_visible' => false]);
        $unapproved = Torrent::factory()->unapproved()->create();
        $ratioBlocked = Torrent::factory()->create();

        $this->storeTorrentPayload($hidden);
        $this->storeTorrentPayload($unapproved);
        $this->storeTorrentPayload($ratioBlocked);

        $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $hidden->id,
        ]))->assertNotFound();

        $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $unapproved->id,
        ]))->assertNotFound();

        $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $ratioBlocked->id,
        ]))->assertForbidden();
    }

    public function test_rss_download_allows_freeleech_when_ratio_bypass_applies(): void
    {
        Storage::fake('torrents');
        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => true,
            'no_history_grace_enabled' => false,
        ]);

        $user = $this->rssUser(uploadedBytes: 100, downloadedBytes: 1_000);
        $torrent = Torrent::factory()->create(['is_freeleech' => true, 'freeleech' => true]);
        $this->storeTorrentPayload($torrent);

        $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $torrent->id,
        ]))->assertOk();
    }

    public function test_rss_xml_includes_safe_download_enclosure_for_eligible_items_only(): void
    {
        $user = $this->rssUser(uploadedBytes: 100, downloadedBytes: 1_000);
        $eligible = Torrent::factory()->create([
            'name' => 'Eligible RSS Freeleech',
            'is_freeleech' => true,
            'freeleech' => true,
        ]);
        $blocked = Torrent::factory()->create(['name' => 'Blocked RSS Torrent']);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token]));
        $downloadUrl = route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $eligible->id,
        ]);

        $response->assertOk();
        $response->assertSee('Eligible RSS Freeleech');
        $response->assertDontSee('Blocked RSS Torrent');
        $response->assertSee('enclosure url="'.$downloadUrl.'"', false);
        $response->assertSee('type="application/x-bittorrent"', false);
        $response->assertDontSee((string) $user->passkey);
        $response->assertDontSee('/torrents/'.$eligible->id.'/download');
        $response->assertDontSee(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $blocked->id,
        ]));
    }

    public function test_language_filters_include_download_links_for_matching_items(): void
    {
        $user = $this->rssUser();
        $match = Torrent::factory()->create(['name' => 'Danish Linked Movie']);
        $miss = Torrent::factory()->create(['name' => 'Swedish Linked Movie']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Danish Linked Movie',
            'language' => 'da',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'Swedish Linked Movie',
            'language' => 'sv',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'language' => 'da']));

        $response->assertOk();
        $response->assertSee('Danish Linked Movie');
        $response->assertDontSee('Swedish Linked Movie');
        $response->assertSee(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $match->id,
        ]));
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

    private function storeTorrentPayload(Torrent $torrent): void
    {
        Storage::disk('torrents')->put(
            $torrent->torrentStoragePath(),
            app(BencodeService::class)->encode([
                'announce' => 'https://tracker.invalid/announce',
                'announce-list' => [
                    ['https://leaked.invalid/announce'],
                ],
                'info' => ['name' => 'demo'],
            ])
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeTorrentPayload(string $payload): array
    {
        $decoded = app(BencodeService::class)->decode($payload);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, bool|float>  $values
     */
    private function setRatioSettings(array $values): void
    {
        if (array_key_exists('enforcement_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::ENFORCEMENT_ENABLED)->update([
                'value' => $values['enforcement_enabled'] ? 'true' : 'false',
            ]);
        }

        if (array_key_exists('minimum_download_ratio', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO)->update([
                'value' => (string) $values['minimum_download_ratio'],
            ]);
        }

        if (array_key_exists('freeleech_bypass_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::FREELEECH_BYPASS_ENABLED)->update([
                'value' => $values['freeleech_bypass_enabled'] ? 'true' : 'false',
            ]);
        }

        if (array_key_exists('no_history_grace_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::NO_HISTORY_GRACE_ENABLED)->update([
                'value' => $values['no_history_grace_enabled'] ? 'true' : 'false',
            ]);
        }
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
