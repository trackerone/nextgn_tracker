<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Torrents\PublishTorrentAction;
use App\Enums\TorrentStatus;
use App\Models\NotificationWatchPreset;
use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use App\Models\UserStat;
use App\Services\Notifications\TorrentWatchPresetMatcher;
use App\Services\Tracker\RatioRulesConfig;
use Tests\TestCase;

final class NotificationWatchPresetTest extends TestCase
{
    public function test_user_can_create_update_enable_disable_and_delete_own_watch_preset(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.watch-presets.store'), [
                'name' => 'Nordic movies',
                'type' => 'movie',
                'language' => 'DANISH',
                'limit' => '100',
                'unsupported' => 'discard-me',
                'user_id' => $otherUser->id,
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('account.watch-presets.index'));

        $preset = NotificationWatchPreset::query()->firstOrFail();

        self::assertSame((int) $user->id, (int) $preset->user_id);
        self::assertTrue($preset->is_enabled);
        self::assertSame([
            'type' => 'movie',
            'language' => 'DANISH',
        ], $preset->filters);
        self::assertArrayNotHasKey('limit', $preset->filters);
        self::assertArrayNotHasKey('unsupported', $preset->filters);

        $this->actingAs($user)
            ->patch(route('account.watch-presets.update', ['preset' => $preset]), [
                'name' => 'Disabled freeleech',
                'q' => 'matrix',
                'freeleech' => '1',
                'is_enabled' => '0',
                'user_id' => $otherUser->id,
            ])
            ->assertRedirect(route('account.watch-presets.index'));

        $preset->refresh();
        self::assertSame((int) $user->id, (int) $preset->user_id);
        self::assertFalse($preset->is_enabled);
        self::assertSame([
            'q' => 'matrix',
            'freeleech' => true,
        ], $preset->filters);

        $this->actingAs($user)
            ->delete(route('account.watch-presets.destroy', ['preset' => $preset]))
            ->assertRedirect(route('account.watch-presets.index'));

        $this->assertDatabaseMissing('notification_watch_presets', ['id' => $preset->id]);
    }

    public function test_user_cannot_manage_another_users_watch_preset(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $preset = NotificationWatchPreset::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('account.watch-presets.edit', ['preset' => $preset]))
            ->assertNotFound();

        $this->actingAs($other)
            ->patch(route('account.watch-presets.update', ['preset' => $preset]), ['name' => 'Stolen'])
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('account.watch-presets.destroy', ['preset' => $preset]))
            ->assertNotFound();
    }

    public function test_matching_torrent_creates_notifications_for_enabled_matching_presets_only(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'The Matrix 1999', 'type' => 'movie']);
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'The Matrix',
            'type' => 'movie',
            'language' => 'Danish',
            'subtitle_language' => 'Swedish',
            'subtitles' => 'Danish, Swedish',
        ]);

        $first = NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Danish movies',
            'filters' => ['type' => 'movie', 'language' => 'da'],
        ]);
        $second = NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Swedish subtitles',
            'filters' => ['subtitles' => 'sv'],
        ]);
        NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Games',
            'filters' => ['type' => 'game'],
        ]);
        NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Disabled',
            'filters' => ['q' => 'Matrix'],
            'is_enabled' => false,
        ]);

        $created = app(TorrentWatchPresetMatcher::class)->notifyMatchesForTorrent($torrent);
        $createdAgain = app(TorrentWatchPresetMatcher::class)->notifyMatchesForTorrent($torrent);

        self::assertSame(2, $created);
        self::assertSame(0, $createdAgain);
        $this->assertDatabaseHas('torrent_watch_notifications', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'notification_watch_preset_id' => $first->id,
        ]);
        $this->assertDatabaseHas('torrent_watch_notifications', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'notification_watch_preset_id' => $second->id,
        ]);
        self::assertSame(2, TorrentWatchNotification::query()->count());
    }

    public function test_hidden_unapproved_and_ratio_ineligible_torrents_do_not_notify(): void
    {
        $user = User::factory()->create();
        NotificationWatchPreset::factory()->create(['user_id' => $user->id, 'filters' => ['q' => 'Match']]);

        $hidden = Torrent::factory()->create(['name' => 'Match hidden', 'is_visible' => false]);
        $unapproved = Torrent::factory()->unapproved()->create(['name' => 'Match pending']);

        self::assertSame(0, app(TorrentWatchPresetMatcher::class)->notifyMatchesForTorrent($hidden));
        self::assertSame(0, app(TorrentWatchPresetMatcher::class)->notifyMatchesForTorrent($unapproved));

        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
        ]);
        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 100,
            'downloaded_bytes' => 1_000,
        ]);

        $ratioDenied = Torrent::factory()->create(['name' => 'Match ratio']);
        self::assertSame(0, app(TorrentWatchPresetMatcher::class)->notifyMatchesForTorrent($ratioDenied));
        self::assertSame(0, TorrentWatchNotification::query()->count());
    }

    public function test_approval_trigger_creates_notification_once(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        NotificationWatchPreset::factory()->create(['user_id' => $user->id, 'filters' => ['q' => 'Publish']]);
        $torrent = Torrent::factory()->unapproved()->create([
            'name' => 'Publish me',
            'status' => TorrentStatus::Pending,
            'is_approved' => false,
        ]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        $this->assertDatabaseHas('torrent_watch_notifications', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
        ]);

        $torrent->refresh()->update(['description' => 'Edited after approval']);

        self::assertSame(1, TorrentWatchNotification::query()->count());
    }

    /**
     * @param  array<string, bool|float>  $values
     */
    private function setRatioSettings(array $values): void
    {
        $map = [
            'enforcement_enabled' => RatioRulesConfig::ENFORCEMENT_ENABLED,
            'minimum_download_ratio' => RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO,
            'freeleech_bypass_enabled' => RatioRulesConfig::FREELEECH_BYPASS_ENABLED,
            'no_history_grace_enabled' => RatioRulesConfig::NO_HISTORY_GRACE_ENABLED,
        ];

        foreach ($values as $key => $value) {
            SiteSetting::query()->updateOrCreate(
                ['key' => $map[$key]],
                [
                    'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                    'type' => is_bool($value) ? 'bool' : 'float',
                ]
            );
        }
    }
}
