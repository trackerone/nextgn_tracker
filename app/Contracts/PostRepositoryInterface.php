<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PostRepositoryInterface
{
    public function paginateForTopic(Topic $topic, int $perPage = 50): LengthAwarePaginator;

    public function createForTopic(Topic $topic, array $attributes): Post;
}
