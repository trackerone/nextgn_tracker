<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($title.'-'.$this->faker->unique()->uuid()),
            'title' => $title,
            'is_locked' => false,
            'is_pinned' => false,
        ];
    }
}
