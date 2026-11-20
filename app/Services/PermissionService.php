<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Settings\SiteSettingsRepository;
use App\Support\Roles\RoleLevel;
use Illuminate\Support\Facades\DB;

final class PermissionService
{
    /**
     * Public feature names map to stable server-side permission keys.
     * Staff and sysop powers are deliberately excluded from editable permissions.
     *
     * @var array<string, string>
     */
    public const MANAGED_FEATURES = [
        'upload' => 'torrent.upload',
        'forum' => 'forum.access',
        'messages' => 'messages.access',
    ];

    /** @var array<string, string> */
    public const FEATURE_LABELS = [
        'upload' => 'Upload releases',
        'forum' => 'Forum',
        'messages' => 'Private messages',
    ];

    /** @var list<string> */
    public const EDITABLE_ROLES = [
        'newbie',
        'user',
        'power_user',
        'uploader',
        'moderator',
        'admin',
    ];

    public static function allow(?User $user, string $permission, mixed $context = null): bool
    {
        if ($user === null || $user->isBanned() || $user->isDisabled()) {
            return false;
        }

        // Staff-only operations must never be granted by a role matrix or personal override.
        if (in_array($permission, ['torrent.edit', 'torrent.delete'], true)) {
            return RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
        }

        $role = self::roleForUser($user);

        if (in_array($permission, self::MANAGED_FEATURES, true)) {
            // The emergency sysop control plane cannot lock itself out.
            if ($role === 'sysop') {
                return true;
            }

            $override = DB::table('user_permission_overrides')
                ->where('user_id', $user->getKey())
                ->where('permission', $permission)
                ->value('allowed');

            if ($override !== null) {
                return (bool) $override;
            }

            return self::permissionsForRole($role)[$permission] ?? false;
        }

        /** @var array<string, list<string>> $rolePermissions */
        $rolePermissions = config('security.role_permissions', []);

        return in_array($permission, $rolePermissions[$role] ?? [], true);
    }

    public static function hasRole(?User $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        $actual = self::roleForUser($user);

        return $actual === $role || ($role === 'admin' && $actual === 'sysop');
    }

    public static function roleForUser(User $user): string
    {
        $level = $user->roleLevel();

        return match (true) {
            $level >= RoleLevel::SYSOP_LEVEL => 'sysop',
            $level >= RoleLevel::ADMIN_LEVEL => 'admin',
            $level >= RoleLevel::MODERATOR_LEVEL => 'moderator',
            $level >= RoleLevel::UPLOADER_LEVEL => 'uploader',
            $level >= 2 => 'power_user',
            $level >= RoleLevel::USER_LEVEL => 'user',
            default => 'newbie',
        };
    }

    /**
     * @return array<string, bool>
     */
    public static function permissionsForRole(string $role): array
    {
        if ($role === 'sysop') {
            return array_fill_keys(array_values(self::MANAGED_FEATURES), true);
        }

        if (! in_array($role, self::EDITABLE_ROLES, true)) {
            return array_fill_keys(array_values(self::MANAGED_FEATURES), false);
        }

        /** @var array<string, list<string>> $defaults */
        $defaults = config('security.role_permissions', []);
        $configured = app(SiteSettingsRepository::class)->getJson('access.roles.'.$role);
        $result = [];

        foreach (self::MANAGED_FEATURES as $permission) {
            $value = $configured[$permission] ?? null;
            $result[$permission] = is_bool($value)
                ? $value
                : in_array($permission, $defaults[$role] ?? [], true);
        }

        return $result;
    }
}
