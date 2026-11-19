<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PostRepositoryInterface;
use App\Http\Requests\Forum\StorePostRequest;
use App\Http\Requests\Forum\UpdatePostRequest;
use App\Models\Post;
use App\Models\Topic;
use App\Services\MarkdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly MarkdownService $markdownService,
        private readonly PostRepositoryInterface $posts,
    ) {}

    public function store(StorePostRequest $request, Topic $topic): Response
    {
        $this->authorize('create', Post::class);

        if ($topic->is_locked) {
            if (! $request->expectsJson()) {
                return redirect()->route('topics.show', ['topic' => $topic->slug])->withErrors(['body_md' => 'Topic is locked.'])->withInput();
            }

            return response()->json([
                'message' => 'Topic is locked.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();
        $data = $request->validated();
        $html = $this->markdownService->render($data['body_md']);

        $post = $this->posts->createForTopic($topic, [
            'user_id' => $user?->getKey(),
            'body_md' => $data['body_md'],
            'body_html' => $html,
        ]);

        if (! $request->expectsJson()) {
            return $this->topicRedirect($post, 'Reply posted.');
        }

        return response()->json($post, Response::HTTP_CREATED);
    }

    public function edit(Post $post): Response
    {
        $this->authorize('update', $post);

        return response()->view('forum.edit-post', ['post' => $post]);
    }

    public function update(UpdatePostRequest $request, Post $post): Response
    {
        $this->authorize('update', $post);

        $data = $request->validated();
        $html = $this->markdownService->render($data['body_md']);

        DB::transaction(function () use ($post, $data, $html) {
            $post->revisions()->create([
                'user_id' => $post->user_id,
                'body_md' => $post->body_md,
            ]);

            $post->update([
                'body_md' => $data['body_md'],
                'body_html' => $html,
                'edited_at' => now(),
            ]);
        });

        if (! $request->expectsJson()) {
            return $this->topicRedirect($post, 'Post updated.');
        }

        return response()->json($post->fresh(['author.role']));
    }

    public function destroy(Request $request, Post $post): Response
    {
        $this->authorize('delete', $post);

        $post->delete();

        if (! $request->expectsJson()) {
            return $this->topicRedirect($post, 'Post removed.');
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function restore(Request $request, Post $post): Response
    {
        $this->authorize('restore', $post);

        $post->restore();

        if (! $request->expectsJson()) {
            return $this->topicRedirect($post, 'Post restored.');
        }

        return response()->json($post->fresh(['author.role']));
    }

    private function topicRedirect(Post $post, string $message): RedirectResponse
    {
        $topic = Topic::query()->whereKey($post->topic_id)->firstOrFail();
        $position = Post::query()->withTrashed()->where('topic_id', $topic->id)->where('id', '<=', $post->id)->count();
        $url = route('topics.show', ['topic' => $topic->slug, 'page' => max(1, (int) ceil($position / 20))]);

        return redirect($url.'#post-'.$post->id)->with('status', $message);
    }
}
