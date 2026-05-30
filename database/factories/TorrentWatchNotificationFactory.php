<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotificationWatchPreset;
use App\Models\Torrent;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TorrentWatchNotification>
 */
final class TorrentWatchNotificationFactory extends Factory
{
    protected $model = TorrentWatchNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'torrent_id' => Torrent::factory(),
            'notification_watch_preset_id' => NotificationWatchPreset::factory(),
            'title' => 'New torrent matched your watch preset: Movies',
            'body' => null,
            'read_at' => null,
        ];
    }
}
