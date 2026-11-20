<?php

declare(strict_types=1);

namespace Tests\Feature\PrivateMessages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PrivateMessageNotificationStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the real database notification channel rather than Notification::fake().
        config()->set('queue.default', 'sync');
    }

    public function test_browser_pm_send_stores_database_notification_and_redirects(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));

        $sender = User::factory()->create(['role' => User::ROLE_USER]);
        $recipient = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sender)
            ->post(route('pm.store'), [
                'recipient_slug' => $recipient->public_slug,
                'body_md' => 'Hej fra senderen',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Message sent.');

        $message = Message::query()->firstOrFail();
        $this->assertSame('Hej fra senderen', $message->body_md);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => NewPrivateMessageNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
        ]);
        $this->get(route('pm.show', $message->conversation_id))->assertOk();
    }

    public function test_browser_pm_reply_saves_another_notification_and_redirects(): void
    {
        $sender = User::factory()->create(['role' => User::ROLE_USER]);
        $recipient = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sender)
            ->post(route('pm.store'), [
                'recipient_slug' => $recipient->public_slug,
                'body_md' => 'First message',
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->firstOrFail();
        $this->actingAs($recipient)
            ->post(route('pm.messages.store', ['conversation' => $conversation->id]), [
                'body_md' => 'Reply from recipient',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reply sent.');

        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'type' => NewPrivateMessageNotification::class,
            'notifiable_id' => $sender->id,
        ]);
    }

    public function test_notification_failure_does_not_turn_a_saved_pm_into_http_500(): void
    {
        $sender = User::factory()->create(['role' => User::ROLE_USER]);
        $recipient = User::factory()->create(['role' => User::ROLE_USER]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Notification storage unavailable'));
        Log::spy();

        $this->actingAs($sender)
            ->post(route('pm.store'), [
                'recipient_slug' => $recipient->public_slug,
                'body_md' => 'Must still deliver',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Message sent.');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'body_md' => 'Must still deliver',
        ]);
        $this->assertDatabaseCount('messages', 1);

        Log::shouldHaveReceived('error')
            ->withArgs(static function (string $message, array $context): bool {
                return $message === 'Private message saved, but notification delivery failed.'
                    && ($context['message_id'] ?? null) !== null
                    && ($context['recipient_user_id'] ?? null) !== null;
            })->once();
    }
}
