<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationWatchPreset;
use App\Models\RssFeedPreset;
use App\Models\Torrent;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use Tests\TestCase;

final class WatchCenterTest extends TestCase
{
    public function test_authenticated_user_can_access_watch_center(): void
    {
        $user = User::factory()->create();

        $this->get(route('my.watch-center'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('my.watch-center'))
            ->assertOk()
            ->assertSee('Watch Center')
            ->assertSee('Watch Presets')
            ->assertSee('RSS Presets')
            ->assertSee('Recent Watch Matches')
            ->assertSee('Notification Inbox');
    }

    public function test_watch_center_shows_users_watch_presets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nordic 2160p movies',
            'filters' => ['type' => 'movie', 'resolution' => '2160p'],
            'is_enabled' => true,
        ]);
        NotificationWatchPreset::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other user watch preset',
        ]);

        $this->actingAs($user)
            ->get(route('my.watch-center'))
            ->assertOk()
            ->assertSee('Nordic 2160p movies')
            ->assertSee('Enabled')
            ->assertSee('resolution')
            ->assertSee('2160p')
            ->assertDontSee('Other user watch preset');
    }

    public function test_watch_center_shows_users_rss_presets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        RssFeedPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Music FLAC RSS',
            'filters' => ['type' => 'music', 'q' => 'flac', 'limit' => 50],
        ]);
        RssFeedPreset::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other user RSS preset',
        ]);

        $this->actingAs($user)
            ->get(route('my.watch-center'))
            ->assertOk()
            ->assertSee('Music FLAC RSS')
            ->assertSee('music')
            ->assertSee('flac')
            ->assertDontSee('Other user RSS preset');
    }

    public function test_watch_center_shows_notifications_and_match_timestamps(): void
    {
        $user = User::factory()->create();
        $preset = NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'name' => 'Movie watch',
        ]);
        $torrent = Torrent::factory()->create(['name' => 'Timestamp Match Torrent']);
        $notification = TorrentWatchNotification::factory()->create([
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'notification_watch_preset_id' => $preset->id,
            'title' => 'New torrent matched your watch preset: Movie watch',
            'created_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($user)
            ->get(route('my.watch-center'))
            ->assertOk()
            ->assertSee('Timestamp Match Torrent')
            ->assertSee('New torrent matched your watch preset: Movie watch')
            ->assertSee('Preset: Movie watch')
            ->assertSee($notification->created_at->toDayDateTimeString())
            ->assertSee('Unread');
    }

    public function test_watch_center_renders_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my.watch-center'))
            ->assertOk()
            ->assertSee('No watch presets yet.')
            ->assertSee('No RSS presets yet.')
            ->assertSee('No watch matches yet.')
            ->assertSee('No notifications yet.');
    }
}
