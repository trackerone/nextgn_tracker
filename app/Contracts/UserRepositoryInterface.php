<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function allStaff(): Collection;

    public function promoteToRole(User $user, string $roleSlug): void;
}
