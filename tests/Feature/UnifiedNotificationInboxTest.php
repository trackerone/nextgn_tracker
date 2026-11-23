<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UnifiedNotificationInboxTest extends TestCase
{
    public function test_inbox_combines_watch_and_private_message_alerts_without_disclosing_message_body(): void
    {
        config()->set('queue.default', 'sync');

        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $recipient->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body_md' => 'A private body that must not appear in the inbox',
            'body_html' => '<p>Private</p>',
        ]);
        $recipient->notify(new NewPrivateMessageNotification($message));

        TorrentWatchNotification::factory()->create([
            'user_id' => $recipient->id,
            'title' => 'Visible watch match',
            'created_at' => now()->subMinutes(2),
        ]);
        TorrentWatchNotification::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other user secret match',
        ]);

        $response = $this->actingAs($recipient)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('Visible watch match')
            ->assertSee('New private message')
            ->assertSee('Open conversation')
            ->assertSee('2 unread')
            ->assertSee('1 search alerts')
            ->assertSee('1 private message alerts')
            ->assertDontSee('A private body that must not appear in the inbox')
            ->assertDontSee('Other user secret match')
            ->assertHeader('Cache-Control', 'no-store, private');

        $html = $response->getContent();
        self::assertIsString($html);
        self::assertLessThan(strpos($html, 'Visible watch match'), strpos($html, 'New private message'));
    }

    public function test_mark_all_reads_both_alert_channels_but_not_messages_or_another_users_notifications(): void
    {
        config()->set('queue.default', 'sync');
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $recipient->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body_md' => 'Unread PM message',
            'body_html' => '<p>Unread PM message</p>',
        ]);
        $recipient->notify(new NewPrivateMessageNotification($message));
        $other->notify(new NewPrivateMessageNotification($message));

        $mine = TorrentWatchNotification::factory()->create(['user_id' => $recipient->id]);
        $theirs = TorrentWatchNotification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($recipient)
            ->post(route('account.notifications.read_all'))
            ->assertRedirect(route('account.notifications.index'))
            ->assertSessionHas('status');

        self::assertNotNull($mine->fresh()->read_at);
        self::assertNull($theirs->fresh()->read_at);
        self::assertSame(0, $recipient->unreadNotifications()->where('type', NewPrivateMessageNotification::class)->count());
        self::assertSame(1, $other->unreadNotifications()->where('type', NewPrivateMessageNotification::class)->count());
        self::assertNull($message->fresh()->read_at, 'Reading alerts must not mark a private message as opened.');
    }

    public function test_private_message_alert_can_be_marked_read_only_by_its_owner(): void
    {
        config()->set('queue.default', 'sync');
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $recipient->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body_md' => 'Read action test',
            'body_html' => '<p>Read action test</p>',
        ]);
        $recipient->notify(new NewPrivateMessageNotification($message));
        $notice = $recipient->notifications()->firstOrFail();

        $this->actingAs($other)->post(route('account.notifications.messages.read', ['notification' => $notice->id]))
            ->assertNotFound();
        self::assertNull($notice->fresh()->read_at);

        $this->actingAs($recipient)->post(route('account.notifications.messages.read', ['notification' => $notice->id]))
            ->assertRedirect(route('account.notifications.index'));
        self::assertNotNull($notice->fresh()->read_at);
        self::assertNull($message->fresh()->read_at);
    }

    public function test_private_message_link_is_hidden_if_conversation_no_longer_belongs_to_user(): void
    {
        config()->set('queue.default', 'sync');
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $intruder->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body_md' => 'No conversation access',
            'body_html' => '<p>No conversation access</p>',
        ]);
        // A legacy or stale alert referencing a conversation this user does
        // not belong to must never produce a privileged navigation link.
        $recipient->notify(new NewPrivateMessageNotification($message));

        $this->actingAs($recipient)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('New private message')
            ->assertDontSee('Open conversation')
            ->assertDontSee('No conversation access');
    }

    public function test_unrelated_laravel_notification_types_are_not_silently_marked_read(): void
    {
        $user = User::factory()->create();
        $unrelatedId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $unrelatedId,
            'type' => 'App\\Notifications\\SomeOtherFutureNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('account.notifications.index'))
            ->assertOk()->assertSee('No notifications yet.');

        $this->post(route('account.notifications.read_all'))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $unrelatedId, 'read_at' => null]);
    }

    public function test_viewing_conversation_marks_matching_pm_alerts_read_without_touching_watch_alerts(): void
    {
        config()->set('queue.default', 'sync');
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $recipient->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body_md' => 'Please read me',
            'body_html' => '<p>Please read me</p>',
        ]);
        $recipient->notify(new NewPrivateMessageNotification($message));
        $watch = TorrentWatchNotification::factory()->create(['user_id' => $recipient->id]);

        $this->actingAs($recipient)->get(route('pm.show', ['conversation' => $conversation->id]))->assertOk();

        self::assertSame(0, $recipient->unreadNotifications()
            ->where('type', NewPrivateMessageNotification::class)->count());
        self::assertNotNull($message->fresh()->read_at);
        self::assertNull($watch->fresh()->read_at);
    }

    public function test_paginated_pm_view_marks_only_the_alerts_for_messages_on_that_page(): void
    {
        config()->set('queue.default', 'sync');
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_a_id' => $sender->id,
            'user_b_id' => $recipient->id,
        ]);

        for ($i = 0; $i < 22; $i++) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body_md' => 'Message '.$i,
                'body_html' => '<p>Message '.$i.'</p>',
            ]);
            $recipient->notify(new NewPrivateMessageNotification($message));
        }

        $this->actingAs($recipient)
            ->get(route('pm.show', ['conversation' => $conversation->id, 'page' => 1]))
            ->assertOk();

        self::assertSame(2, $recipient->unreadNotifications()
            ->where('type', NewPrivateMessageNotification::class)->count());

        $this->get(route('pm.show', ['conversation' => $conversation->id, 'page' => 2]))->assertOk();
        self::assertSame(0, $recipient->unreadNotifications()
            ->where('type', NewPrivateMessageNotification::class)->count());
    }
}
