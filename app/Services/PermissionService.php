<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public static function allow(?User $user, string $permission, mixed $context = null): bool
    {
        if ($user === null) {
            return false;
        }

        $role = self::normalizeRole($user->role);
        $rolePermissions = config('security.role_permissions', []);
        $permissions = $rolePermissions[$role] ?? ($rolePermissions['guest'] ?? []);

        return in_array($permission, $permissions, true);
    }

    public static function hasRole(?User $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        return self::normalizeRole($user->role) === $role;
    }

    private static function normalizeRole(?string $role): string
    {
        return match ($role) {
            null => 'guest',
            User::ROLE_SYSOP, User::ROLE_ADMIN => 'admin',
            User::ROLE_MODERATOR => 'moderator',
            User::ROLE_UPLOADER => 'uploader',
            User::ROLE_POWER_USER, User::ROLE_USER => 'user',
            default => $role,
        };
    }
}
