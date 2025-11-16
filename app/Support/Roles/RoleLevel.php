<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Models\User;

final class RoleLevel
{
    public const SYSOP_LEVEL = 12;

    public const ADMIN_LEVEL = 10;

    public const MODERATOR_LEVEL = 8;

    public const UPLOADER_LEVEL = 5;

    public const USER_LEVEL = 1;

    public const LOWEST_LEVEL = 0;

    /**
     * @var array<string, int>
     */
    private const ROLE_TO_LEVEL = [
        User::ROLE_SYSOP => 12,
        User::ROLE_ADMIN => 10,
        User::ROLE_MODERATOR => 8,
        User::ROLE_UPLOADER => 5,
        User::ROLE_POWER_USER => 4,
        User::ROLE_USER => 1,
    ];

    private function __construct() {}

    public static function levelForUser(User $user): int
    {
        $role = $user->getAttribute('role');

        if (is_string($role) && isset(self::ROLE_TO_LEVEL[$role])) {
            return self::ROLE_TO_LEVEL[$role];
        }

        $legacyRole = $user->relationLoaded('role')
            ? $user->getRelation('role')
            : $user->role()->getResults();

        if ($legacyRole !== null && $legacyRole->level !== null) {
            return (int) $legacyRole->level;
        }

        return self::LOWEST_LEVEL;
    }

    public static function atLeast(User $user, int $minimumLevel): bool
    {
        return self::levelForUser($user) >= $minimumLevel;
    }
}
