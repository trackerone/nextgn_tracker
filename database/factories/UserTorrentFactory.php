<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTorrent>
 */
class UserTorrentFactory extends Factory
{
    protected $model = UserTorrent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'torrent_id' => Torrent::factory(),
            'uploaded' => $this->faker->numberBetween(0, 1_000_000),
            'downloaded' => $this->faker->numberBetween(0, 1_000_000),
            'completed_at' => now(),
            'last_announce_at' => now(),
        ];
    }
}
