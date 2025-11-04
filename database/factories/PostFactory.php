<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $markdown = $this->faker->paragraph();

        return [
            'topic_id' => Topic::factory(),
            'user_id' => User::factory(),
            'body_md' => $markdown,
            'body_html' => '<p>'.e($markdown).'</p>',
            'edited_at' => null,
        ];
    }
}
