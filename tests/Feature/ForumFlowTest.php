<?php

declare(strict_types=1);

namespace Tests\Feature;

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

        $response = $this->get('/forum/topics');

        $response->assertStatus(200);
    }
}
