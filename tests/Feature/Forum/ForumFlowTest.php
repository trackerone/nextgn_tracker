<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use App\Services\MarkdownService;
use App\Services\TopicSlugService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows forum interactions with proper permissions', function (): void {
    $this->seed(RoleSeeder::class);

    $markdown = app(MarkdownService::class);
    $slugger = app(TopicSlugService::class);

    $authorRole = Role::query()->where('slug', 'user1')->firstOrFail();
    $moderatorRole = Role::query()->where('slug', 'mod2')->firstOrFail();
    $adminRole = Role::query()->where('slug', 'admin2')->firstOrFail();

    $existingAuthor = User::factory()->create([
        'role' => $authorRole->slug,
        'role_id' => $authorRole->getKey(),
    ]);

    $topic = Topic::query()->create([
        'user_id' => $existingAuthor->getKey(),
        'slug' => $slugger->generate('Welcome'),
        'title' => 'Welcome',
    ]);

    $topic->posts()->create([
        'user_id' => $existingAuthor->getKey(),
        'body_md' => 'First post',
        'body_html' => $markdown->render('First post'),
    ]);

    $this->actingAs($existingAuthor)
        ->getJson('/topics')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Welcome']);

    $this->actingAs($existingAuthor)
        ->getJson('/topics/'.$topic->slug)
        ->assertOk()
        ->assertJsonFragment(['title' => 'Welcome']);

    $writer = User::factory()->create([
        'role' => $authorRole->slug,
        'role_id' => $authorRole->getKey(),
    ]);

    $createResponse = $this->actingAs($writer)
        ->postJson('/topics', [
            'title' => 'Test topic',
            'body_md' => '# Heading',
        ])
        ->assertCreated();

    $createdTopicId = $createResponse->json('id');
    $createdTopicSlug = $createResponse->json('slug');

    expect($createdTopicSlug)->not()->toBeNull();

    $replyResponse = $this->postJson("/topics/{$createdTopicId}/posts", [
        'body_md' => 'Hello world',
    ])->assertCreated();

    $postId = $replyResponse->json('id');
    expect($postId)->not()->toBeNull();

    $this->patchJson("/posts/{$postId}", [
        'body_md' => 'Opdateret **tekst**',
    ])->assertOk()
        ->assertJsonFragment(['body_md' => 'Opdateret **tekst**'])
        ->assertJsonFragment(['body_html' => $markdown->render('Opdateret **tekst**')]);

    $this->deleteJson("/posts/{$postId}")
        ->assertNoContent();

    $moderator = User::factory()->create([
        'role' => $moderatorRole->slug,
        'role_id' => $moderatorRole->getKey(),
    ]);

    $this->actingAs($moderator)
        ->postJson("/topics/{$createdTopicId}/lock")
        ->assertOk()
        ->assertJsonFragment(['is_locked' => true]);

    $this->postJson("/topics/{$createdTopicId}/pin")
        ->assertOk()
        ->assertJsonFragment(['is_pinned' => true]);

    $admin = User::factory()->create([
        'role' => $adminRole->slug,
        'role_id' => $adminRole->getKey(),
    ]);

    $this->actingAs($admin)
        ->deleteJson("/topics/{$createdTopicId}")
        ->assertForbidden();

    $initialPostId = Post::query()
        ->where('topic_id', $createdTopicId)
        ->whereNull('deleted_at')
        ->value('id');

    expect($initialPostId)->not()->toBeNull();

    $this->actingAs($writer)
        ->deleteJson("/posts/{$initialPostId}")
        ->assertNoContent();

    $restoreTopic = Topic::query()->create([
        'user_id' => $writer->getKey(),
        'slug' => $slugger->generate('Restore test topic'),
        'title' => 'Restore test topic',
    ]);

    $restorePost = $restoreTopic->posts()->create([
        'user_id' => $writer->getKey(),
        'body_md' => 'Should be restored',
        'body_html' => $markdown->render('Should be restored'),
    ]);

    $this->actingAs($writer)
        ->deleteJson("/posts/{$restorePost->id}")
        ->assertNoContent();

    $this->actingAs($moderator)
        ->postJson("/posts/{$restorePost->id}/restore")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $restorePost->id,
            'body_md' => 'Should be restored',
        ]);

    expect($restorePost->fresh()?->deleted_at)->toBeNull();

    $this->actingAs($admin)
        ->deleteJson("/topics/{$createdTopicId}")
        ->assertNoContent();

    expect(Topic::query()->find($createdTopicId))->toBeNull();

    $payload = '<script>alert(1)</script> **bold**';

    $xssResponse = $this->actingAs($writer)
        ->postJson('/topics', [
            'title' => 'XSS test',
            'body_md' => $payload,
        ])
        ->assertCreated();

    $firstPostId = Post::query()
        ->where('topic_id', $xssResponse->json('id'))
        ->value('id');

    expect($firstPostId)->not()->toBeNull();

    $post = Post::query()->findOrFail($firstPostId);

    expect($post->body_html)
        ->not()->toContain('<script>')
        ->toContain('<strong>bold</strong>');
});
