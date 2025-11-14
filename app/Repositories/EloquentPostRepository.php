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
        return $topic->posts()
            ->latest()
            ->paginate($perPage);
    }

    public function createForTopic(Topic $topic, array $attributes): Post
    {
        return $topic->posts()->create($attributes);
    }
}
