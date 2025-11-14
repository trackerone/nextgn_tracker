<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function all(): Collection;

    public function findBySlug(string $slug): ?Role;
}
