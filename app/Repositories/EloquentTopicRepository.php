<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TopicRepositoryInterface;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentTopicRepository implements TopicRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return Topic::query()
            ->with(['author.role'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Topic
    {
        return Topic::query()->find($id);
    }

    public function create(array $attributes): Topic
    {
        $topic = Topic::query()->create($attributes);

        return $topic->fresh(['author.role']);
    }

    public function update(Topic $topic, array $attributes): Topic
    {
        $topic->fill($attributes);
        $topic->save();

        return $topic->fresh(['author.role']);
    }
}
