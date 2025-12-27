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
            /** @var User $admin */
            $admin = User::factory()->create([
                'email' => 'admin@example.com',
                'name' => 'Admin',
            ]);

            /** @var User $user */
            $user = User::factory()->create([
                'email' => 'user@example.com',
                'name' => 'Regular User',
            ]);
        } else {
            /** @var User $admin */
            $admin = User::query()->firstOrFail();

            /** @var User $user */
            $user = User::query()->skip(1)->first() ?? $admin;
        }

        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        if (Topic::query()->count() === 0) {
            Topic::factory()->create([
                'user_id' => (int) $admin->getKey(),
            ]);
        } else {
            Topic::query()->firstOrFail();
        }

        /** @var Topic $topic */
        $topic = Topic::query()->firstOrFail();

        if (Post::query()->count() === 0) {
            Post::factory()->count(3)->create([
                'topic_id' => (int) $topic->getKey(),
                'user_id' => (int) $admin->getKey(),
            ]);
        }

        if (Conversation::query()->count() === 0) {
            Conversation::query()->create([
                'sender_id' => (int) $admin->getKey(),
                'recipient_id' => (int) $user->getKey(),
                'subject' => 'Welcome to NextGN',
            ]);
        }
    }
}
