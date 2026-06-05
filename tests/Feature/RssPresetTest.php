<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RssFeedPreset;
use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Models\UserStat;
use App\Services\BencodeService;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RssPresetTest extends TestCase
{
    public function test_authenticated_user_can_create_update_and_delete_preset(): void
    {
        $user = $this->rssUser();

        $this->actingAs($user)
            ->post(route('account.rss.presets.store'), [
                'name' => 'Nordic movies',
                'type' => 'movie',
                'language' => 'DANISH',
                'limit' => '1000',
                'unsupported' => 'discard-me',
                'public_id' => '00000000-0000-0000-0000-000000000000',
                'user_id' => User::factory()->create()->id,
            ])
            ->assertRedirect(route('account.rss.index'));

        $preset = RssFeedPreset::query()->firstOrFail();

        self::assertSame((int) $user->id, (int) $preset->user_id);
        self::assertNotEmpty($preset->public_id);
        self::assertNotSame('00000000-0000-0000-0000-000000000000', $preset->public_id);
        self::assertSame([
            'type' => 'movie',
            'language' => 'DANISH',
            'limit' => 100,
        ], $preset->filters);
        self::assertArrayNotHasKey('unsupported', $preset->filters);

        $originalPublicId = (string) $preset->public_id;

        $this->actingAs($user)
            ->patch(route('account.rss.presets.update', ['preset' => $preset]), [
                'name' => 'Danish freeleech',
                'public_id' => '11111111-1111-1111-1111-111111111111',
                'freeleech' => '1',
                'q' => 'matrix',
            ])
            ->assertRedirect(route('account.rss.index'));

        $preset->refresh();
        self::assertSame('Danish freeleech', $preset->name);
        self::assertSame($originalPublicId, $preset->public_id);
        self::assertSame([
            'q' => 'matrix',
            'freeleech' => true,
        ], $preset->filters);

        $this->actingAs($user)
            ->delete(route('account.rss.presets.destroy', ['preset' => $preset]))
            ->assertRedirect(route('account.rss.index'));

        $this->assertDatabaseMissing('rss_feed_presets', ['id' => $preset->id]);
    }

    public function test_user_cannot_manage_another_users_preset(): void
    {
        $owner = $this->rssUser();
        $other = $this->rssUser();
        $preset = RssFeedPreset::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('account.rss.presets.edit', ['preset' => $preset]))
            ->assertNotFound();

        $this->actingAs($other)
            ->patch(route('account.rss.presets.update', ['preset' => $preset]), [
                'name' => 'Stolen',
            ])
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('account.rss.presets.destroy', ['preset' => $preset]))
            ->assertNotFound();

        self::assertSame((int) $owner->id, (int) $preset->refresh()->user_id);
    }

    public function test_preset_feed_requires_valid_token_and_owned_preset(): void
    {
        $owner = $this->rssUser();
        $other = $this->rssUser();
        $preset = RssFeedPreset::factory()->create(['user_id' => $owner->id]);

        $this->get(route('rss.presets.feed', [
            'token' => 'notarealtoken',
            'preset' => $preset->public_id,
        ]))->assertNotFound();

        $this->get(route('rss.presets.feed', [
            'token' => $other->rss_token,
            'preset' => $preset->public_id,
        ]))->assertNotFound();
    }

    public function test_valid_preset_feed_matches_equivalent_query_feed_and_has_secure_enclosures(): void
    {
        $user = $this->rssUser();
        $match = Torrent::factory()->create(['name' => 'Matrix Preset Match']);
        $miss = Torrent::factory()->create(['name' => 'Matrix Preset Miss']);
        $preset = RssFeedPreset::factory()->create([
            'user_id' => $user->id,
            'filters' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'language' => 'da',
                'subtitle_language' => 'da',
            ],
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Matrix Preset Match',
            'type' => 'movie',
            'resolution' => '2160p',
            'language' => 'da',
            'subtitle_language' => 'da',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'title' => 'Matrix Preset Miss',
            'type' => 'movie',
            'resolution' => '1080p',
            'language' => 'en',
            'subtitle_language' => 'en',
        ]);

        $queryResponse = $this->get(route('rss.feed', [
            'token' => $user->rss_token,
            'q' => 'matrix',
            'type' => 'movie',
            'resolution' => '2160p',
            'language' => 'da',
            'subtitle_language' => 'da',
        ]));
        $presetResponse = $this->get(route('rss.presets.feed', [
            'token' => $user->rss_token,
            'preset' => $preset->public_id,
        ]));

        $queryResponse->assertOk();
        $presetResponse->assertOk();
        self::assertSame(substr_count($queryResponse->getContent(), '<item>'), substr_count($presetResponse->getContent(), '<item>'));
        $presetResponse->assertSee('Matrix Preset Match');
        $presetResponse->assertDontSee('Matrix Preset Miss');
        $presetResponse->assertSee(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $match->id,
        ]));
        $presetResponse->assertDontSee((string) $user->passkey);
    }

    public function test_rotating_token_invalidates_preset_feed_url(): void
    {
        $user = $this->rssUser();
        $oldToken = (string) $user->rss_token;
        $preset = RssFeedPreset::factory()->create(['user_id' => $user->id]);

        $this->get(route('rss.presets.feed', [
            'token' => $oldToken,
            'preset' => $preset->public_id,
        ]))->assertOk();

        $this->actingAs($user)->post(route('account.rss.rotate'))->assertRedirect(route('account.rss.index'));
        $user->refresh();

        $this->get(route('rss.presets.feed', [
            'token' => $oldToken,
            'preset' => $preset->public_id,
        ]))->assertNotFound();
        $this->get(route('rss.presets.feed', [
            'token' => $user->rss_token,
            'preset' => $preset->public_id,
        ]))->assertOk();
    }

    public function test_preset_feed_respects_hidden_unapproved_and_ratio_ineligible_torrents(): void
    {
        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => true,
            'no_history_grace_enabled' => false,
        ]);

        $user = $this->rssUser(uploadedBytes: 100, downloadedBytes: 1_000);
        $eligible = Torrent::factory()->create(['name' => 'Preset Eligible Freeleech', 'is_freeleech' => true, 'freeleech' => true]);
        $blocked = Torrent::factory()->create(['name' => 'Preset Ratio Blocked']);
        $hidden = Torrent::factory()->unapproved()->create(['name' => 'Preset Hidden Pending']);
        $preset = RssFeedPreset::factory()->create(['user_id' => $user->id, 'filters' => []]);

        $response = $this->get(route('rss.presets.feed', [
            'token' => $user->rss_token,
            'preset' => $preset->public_id,
        ]));

        $response->assertOk();
        $response->assertSee($eligible->name);
        $response->assertDontSee($blocked->name);
        $response->assertDontSee($hidden->name);
    }

    public function test_existing_raw_feed_and_rss_download_routes_still_work(): void
    {
        Storage::fake('torrents');

        $user = $this->rssUser();
        $torrent = Torrent::factory()->create(['name' => 'Regression Download']);
        $this->storeTorrentPayload($torrent);

        $this->get(route('rss.feed', ['token' => $user->rss_token]))->assertOk();
        $this->get(route('rss.torrents.download', [
            'token' => $user->rss_token,
            'torrent' => $torrent->id,
        ]))->assertOk();
    }

    private function storeTorrentPayload(Torrent $torrent): void
    {
        Storage::disk('torrents')->put(
            $torrent->torrentStoragePath(),
            app(BencodeService::class)->encode([
                'announce' => 'https://tracker.invalid/announce',
                'info' => ['name' => 'demo'],
            ])
        );
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
