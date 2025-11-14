<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_conversations(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $conversation = Conversation::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => 'Test conversation',
        ]);

        $this->actingAs($sender);

        $response = $this->get('/pm');

        $response->assertStatus(200);
    }
}
