<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Roles\RoleLevel;

class PermissionService
{
    public static function allow(?User $user, string $permission, mixed $context = null): bool
    {
        if ($user === null) {
            return false;
        }

        /**
         * Staff moderation actions should be allowed based on role level, not only config.
         * This keeps core moderation flows stable even if role_permissions config is incomplete.
         */
        if (in_array($permission, ['torrent.edit', 'torrent.delete'], true)) {
            return RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
        }

        $role = self::normalizeRole($user->resolveRoleIdentifier());

        /** @var array<string, array<int, string>> $rolePermissions */
        $rolePermissions = config('security.role_permissions', []);

        $permissions = $rolePermissions[$role] ?? ($rolePermissions['guest'] ?? []);

        return in_array($permission, $permissions, true);
    }

    public static function hasRole(?User $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        return self::normalizeRole($user->resolveRoleIdentifier()) === $role;
    }

    private static function normalizeRole(string|Role|null $role): string
    {
        if ($role instanceof Role) {
            $role = $role->slug ?? $role->name;
        }

        if ($role !== null) {
            $role = User::roleFromLegacySlug($role);
        }

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
