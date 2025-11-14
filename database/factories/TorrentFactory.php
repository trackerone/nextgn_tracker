<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Torrent>
 */
class TorrentFactory extends Factory
{
    protected $model = Torrent::class;

    public function definition(): array
    {
        $name = $this->faker->sentence(3);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->uuid()),
            'info_hash' => Str::upper(bin2hex(random_bytes(20))),
            'size' => $this->faker->numberBetween(1_000_000, 50_000_000_000),
            'files_count' => $this->faker->numberBetween(1, 400),
            'seeders' => $this->faker->numberBetween(0, 5_000),
            'leechers' => $this->faker->numberBetween(0, 5_000),
            'completed' => $this->faker->numberBetween(0, 10_000),
            'is_visible' => true,
        ];
    }
}
