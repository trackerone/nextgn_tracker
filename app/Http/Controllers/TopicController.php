<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Forum\StoreTopicRequest;
use App\Models\Topic;
use App\Services\MarkdownService;
use App\Services\TopicSlugService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TopicController extends Controller
{
    public function __construct(
        private readonly TopicSlugService $slugService,
        private readonly MarkdownService $markdownService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $topics = Topic::query()
            ->with(['author.role'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(perPage: 20);

        return response()->json($this->formatTopics($topics));
    }

    public function show(string $slug): JsonResponse
    {
        $topic = Topic::query()
            ->where('slug', $slug)
            ->with(['author.role'])
            ->firstOrFail();

        $posts = $topic->posts()
            ->with(['author.role'])
            ->withTrashed()
            ->orderBy('created_at')
            ->paginate(perPage: 20);

        return response()->json([
            'topic' => $topic,
            'posts' => $posts,
        ]);
    }

    public function store(StoreTopicRequest $request): JsonResponse
    {
        $this->authorize('create', Topic::class);

        $user = $request->user();
        $data = $request->validated();
        $slug = $this->slugService->generate($data['title']);

        $topic = DB::transaction(function () use ($user, $data, $slug) {
            $topic = Topic::query()->create([
                'user_id' => $user?->getKey(),
                'slug' => $slug,
                'title' => $data['title'],
            ]);

            $html = $this->markdownService->render($data['body_md']);

            $topic->posts()->create([
                'user_id' => $user?->getKey(),
                'body_md' => $data['body_md'],
                'body_html' => $html,
            ]);

            return $topic;
        });

        $topic->load(['author.role', 'posts.author.role']);

        return response()->json($topic, Response::HTTP_CREATED);
    }

    public function update(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:140'],
        ]);

        $topic->update(['title' => $validated['title']]);

        return response()->json($topic->fresh(['author.role']));
    }

    public function toggleLock(Topic $topic): JsonResponse
    {
        $this->authorize('lock', $topic);

        $topic->update(['is_locked' => ! $topic->is_locked]);

        return response()->json($topic->fresh(['author.role']));
    }

    public function togglePin(Topic $topic): JsonResponse
    {
        $this->authorize('pin', $topic);

        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        return response()->json($topic->fresh(['author.role']));
    }

    public function destroy(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('delete', $topic);

        if ($topic->posts()->exists()) {
            return response()->json([
                "message" => 'Topic must be empty before deletion.',
            ], Response::HTTP_FORBIDDEN);
        }

        $topic->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTopics(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
