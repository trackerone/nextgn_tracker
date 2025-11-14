<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TopicRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    public function findById(int $id): ?Topic;

    public function create(array $attributes): Topic;

    public function update(Topic $topic, array $attributes): Topic;
}
