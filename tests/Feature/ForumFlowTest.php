<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_topics(): void
    {
        $user = User::factory()->create();
        Topic::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->get('/topics');

        $response->assertStatus(200);
    }

    public function test_topic_listing_includes_precomputed_activity_metadata(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();
        $latestAuthor = User::factory()->create();
        $topic = Topic::factory()->create(['user_id' => $author->getKey()]);

        Post::factory()->create([
            'topic_id' => $topic->getKey(),
            'user_id' => $author->getKey(),
            'created_at' => now()->subMinutes(10),
        ]);
        $latest = Post::factory()->create([
            'topic_id' => $topic->getKey(),
            'user_id' => $latestAuthor->getKey(),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/topics')
            ->assertOk()
            ->assertJsonPath('data.0.posts_count', 2)
            ->assertJsonPath('data.0.latest_post.id', $latest->getKey())
            ->assertJsonPath('data.0.latest_post.author.id', $latestAuthor->getKey());
    }
}
