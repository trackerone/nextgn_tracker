<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Peer>
 */
class PeerFactory extends Factory
{
    protected $model = Peer::class;

    public function definition(): array
    {
        return [
            'torrent_id' => Torrent::factory(),
            'user_id' => User::factory(),
            'peer_id' => $this->faker->regexify('[A-Za-z0-9]{20}'),
            'ip' => $this->faker->ipv4(),
            'port' => $this->faker->numberBetween(1024, 65535),
            'uploaded' => $this->faker->numberBetween(0, 5_000_000_000),
            'downloaded' => $this->faker->numberBetween(0, 5_000_000_000),
            'left' => $this->faker->numberBetween(0, 5_000_000_000),
            'is_seeder' => $this->faker->boolean(),
            'last_announce_at' => now(),
        ];
    }
}
