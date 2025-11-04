<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Notifications\PrivateMessageDigestNotification;
use App\Services\MarkdownService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->userRole = Role::query()->where('slug', 'user1')->firstOrFail();
});

it('allows starting a conversation and notifies the recipient', function (): void {
    Notification::fake();

    $sender = User::factory()->create(['role_id' => $this->userRole->getKey()]);
    $recipient = User::factory()->create(['role_id' => $this->userRole->getKey()]);

    $response = $this->actingAs($sender)
        ->postJson('/pm', [
            'recipient_id' => $recipient->getKey(),
            'body_md' => 'Hej **verden**',
        ])
        ->assertCreated();

    $conversationId = $response->json('conversation.id');
    expect($conversationId)->not()->toBeNull();

    $conversation = Conversation::query()->findOrFail($conversationId);
    expect($conversation->last_message_at)->not()->toBeNull();

    $message = Message::query()->where('conversation_id', $conversationId)->first();
    expect($message)->not()->toBeNull();
    expect($message->body_html)
        ->toContain('<strong>verden</strong>')
        ->not()->toContain('<script>');

    Notification::assertSentTo($recipient, NewPrivateMessageNotification::class);
});

it('allows replying to a conversation and marks messages as read when viewed', function (): void {
    Notification::fake();

    $userA = User::factory()->create(['role_id' => $this->userRole->getKey()]);
    $userB = User::factory()->create(['role_id' => $this->userRole->getKey()]);

    $createResponse = $this->actingAs($userA)
        ->postJson('/pm', [
            'recipient_id' => $userB->getKey(),
            'body_md' => 'Første besked',
        ])
        ->assertCreated();

    $conversationId = $createResponse->json('conversation.id');

    $this->actingAs($userB)
        ->postJson("/pm/{$conversationId}/messages", [
            'body_md' => 'Svar fra B',
        ])
        ->assertCreated();

    Notification::assertSentTo($userA, NewPrivateMessageNotification::class);

    $showResponse = $this->actingAs($userA)
        ->getJson("/pm/{$conversationId}")
        ->assertOk();

    $messages = $showResponse->json('messages');
    expect($messages)->toBeArray();
    $latest = collect($messages)->last();
    expect($latest['read_at'])->not()->toBeNull();
});

it('forbids non-participants from accessing conversations', function (): void {
    $userA = User::factory()->create(['role_id' => $this->userRole->getKey()]);
    $userB = User::factory()->create(['role_id' => $this->userRole->getKey()]);
    $intruder = User::factory()->create(['role_id' => $this->userRole->getKey()]);

    $conversation = Conversation::factory()->create([
        'user_a_id' => $userA->getKey(),
        'user_b_id' => $userB->getKey(),
    ]);

    $this->actingAs($intruder)
        ->getJson("/pm/{$conversation->getKey()}")
        ->assertForbidden();

    $this->actingAs($intruder)
        ->postJson("/pm/{$conversation->getKey()}/messages", [
            'body_md' => 'Hej',
        ])
        ->assertForbidden();
});

it('dispatches digest notifications for unread messages', function (): void {
    Notification::fake();

    $markdown = app(MarkdownService::class);

    $sender = User::factory()->create(['role_id' => $this->userRole->getKey()]);
    $recipient = User::factory()->create(['role_id' => $this->userRole->getKey()]);

    $conversation = Conversation::query()->create([
        'user_a_id' => min($sender->getKey(), $recipient->getKey()),
        'user_b_id' => max($sender->getKey(), $recipient->getKey()),
        'last_message_at' => now(),
    ]);

    $conversation->messages()->create([
        'sender_id' => $sender->getKey(),
        'body_md' => 'Digest test',
        'body_html' => $markdown->render('Digest test'),
        'created_at' => now()->subHours(2),
    ]);

    Artisan::call('pm:digest daily');

    Notification::assertSentToTimes($recipient, PrivateMessageDigestNotification::class, 1);
});
