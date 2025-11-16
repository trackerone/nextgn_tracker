<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\RoleRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    )
    {
    }

    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return User::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }

    public function allStaff(): Collection
    {
        return User::query()
            ->staff()
            ->orderBy('name')
            ->get();
    }

    public function promoteToRole(User $user, string $roleSlug): void
    {
        $role = $this->roles->findBySlug($roleSlug);

        if (!$role) {
            return;
        }

        $user->forceFill([
            'role' => User::roleFromLegacySlug($role->slug),
            'role_id' => $role->getKey(),
        ])->save();
    }
}
