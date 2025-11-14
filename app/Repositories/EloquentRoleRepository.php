<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\RoleRepositoryInterface;
use App\Models\Role;
use Illuminate\Support\Collection;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::query()
            ->orderBy('level', 'desc')
            ->get();
    }

    public function findBySlug(string $slug): ?Role
    {
        return Role::query()
            ->where('slug', $slug)
            ->first();
    }
}
