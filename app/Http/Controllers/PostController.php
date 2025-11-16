<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PostRepositoryInterface;
use App\Http\Requests\Forum\StorePostRequest;
use App\Http\Requests\Forum\UpdatePostRequest;
use App\Models\Post;
use App\Models\Topic;
use App\Services\MarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly MarkdownService $markdownService,
        private readonly PostRepositoryInterface $posts,
    ) {}

    public function store(StorePostRequest $request, Topic $topic): JsonResponse
    {
        $this->authorize('create', Post::class);

        if ($topic->is_locked) {
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

        return response()->json($post, Response::HTTP_CREATED);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
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

        return response()->json($post->fresh(['author.role']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function restore(Post $post): JsonResponse
    {
        $this->authorize('restore', $post);

        $post->restore();

        return response()->json($post->fresh(['author.role']));
    }
}
