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
    private const SLUG_TO_LEVEL = [
        'sysop' => 12,
        'admin2' => 11,
        'admin1' => 10,
        'mod2' => 9,
        'mod1' => 8,
        'uploader3' => 7,
        'uploader2' => 6,
        'uploader1' => 5,
        'user4' => 4,
        'user3' => 3,
        'user2' => 2,
        'user1' => 1,
        'newbie' => 0,
    ];

    /**
     * @var array<int, string>
     */
    private const LEVEL_TO_SLUG = [
        12 => 'sysop',
        11 => 'admin2',
        10 => 'admin1',
        9 => 'mod2',
        8 => 'mod1',
        7 => 'uploader3',
        6 => 'uploader2',
        5 => 'uploader1',
        4 => 'user4',
        3 => 'user3',
        2 => 'user2',
        1 => 'user1',
        0 => 'newbie',
    ];

    private function __construct()
    {
    }

    public static function forSlug(string $slug): ?int
    {
        return self::SLUG_TO_LEVEL[$slug] ?? null;
    }

    public static function forLevel(int $level): ?string
    {
        return self::LEVEL_TO_SLUG[$level] ?? null;
    }

    public static function levelForUser(User $user): int
    {
        $role = $user->role;

        if ($role === null) {
            return self::LOWEST_LEVEL;
        }

        if ($role->level !== null) {
            return (int) $role->level;
        }

        if ($role->slug !== null) {
            return self::forSlug($role->slug) ?? self::LOWEST_LEVEL;
        }

        return self::LOWEST_LEVEL;
    }

    public static function atLeast(User $user, int $minimumLevel): bool
    {
        return self::levelForUser($user) >= $minimumLevel;
    }
}
