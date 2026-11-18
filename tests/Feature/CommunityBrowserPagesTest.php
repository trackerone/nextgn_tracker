<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CommunityBrowserPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_forum_and_inbox_are_html_in_a_browser_and_json_when_requested(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/topics')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Community forum')->assertSee('No topics yet')->assertSee(route('topics.create'));
        $this->get('/pm')->assertOk()->assertSee('Your inbox is empty')->assertSee(route('pm.create'))
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/topics')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->getJson('/pm')->assertOk()->assertExactJson([]);
    }

    public function test_guest_and_unverified_forum_write_permissions_remain_enforced(): void
    {
        $this->get('/topics')->assertRedirect(route('login'));
        $this->get('/pm')->assertRedirect(route('login'));
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user)->get('/topics')->assertOk()->assertSee('A verified email is required');
        $this->get(route('topics.create'))->assertForbidden();
        $this->post('/topics', ['title' => 'Not allowed', 'body_md' => 'Not verified'])->assertForbidden();
    }

    public function test_browser_can_create_read_reply_and_edit_without_landing_on_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('topics.create'))->assertOk()->assertSee('Opening post');
        $this->post('/topics', ['title' => 'A browser topic', 'body_md' => 'Opening **words**'])->assertRedirect();
        $topic = Topic::query()->firstOrFail();
        $this->get(route('topics.show', ['topic' => $topic->slug]))->assertOk()->assertSee('A browser topic')->assertSee('<strong>words</strong>', false)->assertSee('Post reply');
        $this->post(route('topics.posts.store', $topic->id), ['body_md' => 'My browser reply'])->assertRedirect();
        $post = Post::query()->latest('id')->firstOrFail();
        $this->get(route('posts.edit', $post->id))->assertOk()->assertSee('My browser reply');
        $this->patch(route('posts.update', $post->id), ['body_md' => 'Updated browser reply'])->assertRedirect();
        $this->assertDatabaseHas('post_revisions', ['post_id' => $post->id, 'body_md' => 'My browser reply']);
        $this->patch(route('topics.update', $topic->id), ['title' => 'Renamed discussion'])->assertRedirect();
        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'title' => 'Renamed discussion']);
        $this->getJson(route('topics.show', ['topic' => $topic->slug]))->assertOk()->assertJsonPath('topic.id', $topic->id);
    }

    public function test_forum_moderation_uses_policies_and_deleted_content_is_not_rendered(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $topic = Topic::factory()->create(['user_id' => $owner->id]);
        $post = Post::factory()->create(['topic_id' => $topic->id, 'user_id' => $owner->id, 'body_md' => 'Removed confidential text']);
        $url = route('topics.show', ['topic' => $topic->slug]);
        $this->actingAs($other)->get(route('posts.edit', $post->id))->assertForbidden();
        $this->post(route('topics.lock', $topic->id))->assertForbidden();
        $this->actingAs($moderator)->post(route('topics.lock', $topic->id))->assertRedirect($url);
        $this->post(route('topics.pin', $topic->id))->assertRedirect($url);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Replies are disabled.')->assertDontSee('Post reply');
        $this->post(route('topics.posts.store', $topic->id), ['body_md' => 'Blocked reply'])->assertSessionHasErrors('body_md');
        $this->delete(route('posts.destroy', $post->id))->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Post removed.')->assertDontSee('Removed confidential text');
        $this->actingAs($moderator)->post(route('posts.restore', $post->id))->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Removed confidential text');
    }

    public function test_forum_pagination_and_reply_redirect_reach_the_actual_post(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();
        Post::factory()->count(20)->create(['topic_id' => $topic->id]);
        $this->actingAs($user)->post(route('topics.posts.store', $topic->id), ['body_md' => 'Page two reply'])->assertRedirect();
        $post = Post::query()->latest('id')->firstOrFail();
        $this->get(route('topics.show', ['topic' => $topic->slug, 'page' => 2]))->assertOk()->assertSee('Page two reply');
        $this->assertSame(21, $topic->posts()->count());
        $this->assertNotNull($post);
    }

    public function test_browser_messages_use_profile_names_and_preserve_participant_privacy(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $this->actingAs($sender)->get(route('pm.create', ['recipient' => $recipient->publicProfileRouteKey()]))->assertOk()->assertSee($recipient->name)->assertSee('recipient_slug')->assertDontSee('Recipient ID');
        $this->post('/pm', ['recipient_slug' => $recipient->public_slug, 'body_md' => 'Private browser message'])->assertRedirect();
        $conversation = Conversation::query()->firstOrFail();
        $this->get('/pm')->assertOk()->assertSee($recipient->name)->assertSee('Private browser message');
        $this->get(route('pm.show', $conversation->id))->assertOk()->assertSee('Private browser message')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($outsider)->get('/pm')->assertOk()->assertDontSee('Private browser message');
        $this->get(route('pm.show', $conversation->id))->assertForbidden();
        $this->post(route('pm.messages.store', $conversation->id), ['body_md' => 'Intruding reply'])->assertForbidden();
        $this->actingAs($recipient)->post(route('pm.messages.store', $conversation->id), ['body_md' => 'Private answer'])->assertRedirect();
        $this->getJson(route('pm.show', $conversation->id))->assertOk()->assertJsonPath('messages.1.body_md', 'Private answer');
    }

    public function test_invalid_or_self_recipient_does_not_create_a_conversation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/pm', ['recipient_slug' => 'no-such-member', 'body_md' => 'Hello there'])->assertSessionHasErrors('recipient_slug');
        $this->post('/pm', ['recipient_slug' => $user->public_slug, 'body_md' => 'Hello myself'])->assertSessionHasErrors('recipient_slug');
        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_only_visible_incoming_messages_are_marked_read_and_replies_reach_last_page(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_a_id' => min($user->id, $other->id), 'user_b_id' => max($user->id, $other->id)]);
        Message::factory()->count(21)->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'read_at' => null]);
        $this->actingAs($user)->get('/pm')->assertOk()->assertSee('21 unread');
        $this->get(route('pm.show', $conversation->id))->assertOk();
        $this->assertSame(1, $conversation->messages()->whereNull('read_at')->count());
        $this->post(route('pm.messages.store', $conversation->id), ['body_md' => 'Latest browser answer'])->assertRedirect();
        $message = Message::query()->latest('id')->firstOrFail();
        $this->get(route('pm.show', ['conversation' => $conversation->id, 'page' => 2]))->assertOk()->assertSee('Latest browser answer');
        $this->assertSame(1, $conversation->messages()->whereNull('read_at')->count());
        $this->assertSame($user->id, $message->sender_id);
    }

    public function test_browser_renders_sanitized_markdown_instead_of_trusting_stored_html(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();
        Post::factory()->create(['topic_id' => $topic->id, 'body_md' => 'Safe **forum**', 'body_html' => '<script>storedForumAttack()</script>']);
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_a_id' => min($user->id, $other->id), 'user_b_id' => max($user->id, $other->id)]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'body_md' => 'Safe **message**', 'body_html' => '<script>storedMessageAttack()</script>']);
        $this->actingAs($user)->get(route('topics.show', ['topic' => $topic->slug]))->assertOk()->assertSee('<strong>forum</strong>', false)->assertDontSee('storedForumAttack');
        $this->get(route('pm.show', $conversation->id))->assertOk()->assertSee('<strong>message</strong>', false)->assertDontSee('storedMessageAttack');
    }
}
