<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\PostRepositoryInterface;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function paginateForTopic(Topic $topic, int $perPage = 50): LengthAwarePaginator
    {
        return Post::query()
            ->withTrashed()
            ->with(['author.role'])
            ->where('topic_id', $topic->getKey())
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public function createForTopic(Topic $topic, array $attributes): Post
    {
        /** @var Post $post */
        $post = $topic->posts()->create($attributes);

        /** @var Post $fresh */
        $fresh = $post->fresh(['author.role']);

        return $fresh;
    }
}
