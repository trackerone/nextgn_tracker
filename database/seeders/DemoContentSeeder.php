<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->count() === 0) {
            $admin = User::factory()->create([
                'email' => 'admin@example.com',
                'name' => 'Admin',
            ]);

            $user = User::factory()->create([
                'email' => 'user@example.com',
                'name' => 'Regular User',
            ]);
        } else {
            $admin = User::query()->first();
            $user = User::query()->skip(1)->first() ?? $admin;
        }

        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        if (Topic::query()->count() === 0) {
            $topic = Topic::factory()->create([
                'user_id' => $admin->id,
            ]);
        } else {
            $topic = Topic::query()->first();
        }

        if (Post::query()->count() === 0) {
            Post::factory()->count(3)->create([
                'topic_id' => $topic->id,
                'user_id' => $admin->id,
            ]);
        }

        if (Conversation::query()->count() === 0) {
            Conversation::query()->create([
                'sender_id' => $admin->id,
                'recipient_id' => $user->id,
                'subject' => 'Welcome to NextGN',
            ]);
        }
    }
}
