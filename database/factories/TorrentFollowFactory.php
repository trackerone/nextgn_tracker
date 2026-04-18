<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TorrentFollow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TorrentFollow>
 */
class TorrentFollowFactory extends Factory
{
    protected $model = TorrentFollow::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'normalized_title' => Str::of($title)->lower()->replaceMatches('/[^a-z0-9]+/i', ' ')->squish()->value(),
            'type' => $this->faker->optional()->randomElement(['movie', 'tv']),
            'resolution' => $this->faker->optional()->randomElement(['2160p', '1080p', '720p']),
            'source' => $this->faker->optional()->randomElement(['WEB-DL', 'BLURAY', 'HDTV']),
            'year' => $this->faker->optional()->numberBetween(1980, 2030),
        ];
    }
}

