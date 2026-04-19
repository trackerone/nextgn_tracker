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
            'type' => null,
            'resolution' => null,
            'source' => null,
            'year' => null,
            'last_checked_at' => null,
        ];
    }
}
