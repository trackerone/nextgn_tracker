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
            ->select(['id', 'user_id', 'slug', 'title', 'is_locked', 'is_pinned', 'created_at', 'updated_at'])
            ->with([
                'author:id,name,role_id',
                'author.role:id,name',
                'latestPost' => static fn ($query) => $query
                    ->select(['posts.id', 'posts.topic_id', 'posts.user_id', 'posts.created_at'])
                    ->with('author:id,name'),
            ])
            ->withCount('posts')
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

        return $this->loadSummaryRelations($topic);
    }

    public function update(Topic $topic, array $attributes): Topic
    {
        $topic->fill($attributes);
        $topic->save();

        return $this->loadSummaryRelations($topic);
    }

    private function loadSummaryRelations(Topic $topic): Topic
    {
        $topic->load([
            'author:id,name,role_id',
            'author.role:id,name',
            'latestPost' => static fn ($query) => $query
                ->select(['posts.id', 'posts.topic_id', 'posts.user_id', 'posts.created_at'])
                ->with('author:id,name'),
        ]);
        $topic->loadCount('posts');

        return $topic;
    }
}
