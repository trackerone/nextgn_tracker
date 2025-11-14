<?php

declare(strict_types=1);

namespace Tests\Feature\PrivateMessages;

use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder);

        $this->getJson("/pm/{$conversation->getKey()}")
            ->assertStatus(403);
    }

    public function test_participant_can_view_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->load('userA');
        $participant = $conversation->userA;

        $this->actingAs($participant);

        $this->getJson("/pm/{$conversation->getKey()}")
            ->assertOk();
    }
}
