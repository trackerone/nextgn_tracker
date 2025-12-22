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
        // Legacy slugs (tests forventer disse)
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

        // Normaliserede app-roller
        // IMPORTANT: default "user" must be >= 1 to pass role.min:1 routes (e.g., /pm).
        'user' => 1,
        'power_user' => 2,
        'uploader' => 5,
        'moderator' => 8,
        'admin' => 10,
        // sysop er allerede dækket
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

    private function __construct() {}

    public static function forSlug(?string $slug): ?int
    {
        if (! is_string($slug) || trim($slug) === '') {
            return null;
        }

        $slug = strtolower(trim($slug));

        return self::SLUG_TO_LEVEL[$slug] ?? null;
    }

    public static function forLevel(int $level): ?string
    {
        return self::LEVEL_TO_SLUG[$level] ?? null;
    }

    public static function levelForUser(User $user): int
    {
        /**
         * KRITISK:
         * RoleAccessTest sætter/forventer legacy slug i users.role.
         * Derfor skal vi mappe på users.role FØRST - men default 'user'
         * må ikke overstyre en tilknyttet role_id/role relation.
         */
        $roleAttribute = $user->getAttribute('role');
        $roleAttributeSlug = is_string($roleAttribute) ? strtolower(trim($roleAttribute)) : null;

        // 1) Primært: users.role hvis den er andet end default 'user'
        if (is_string($roleAttributeSlug) && $roleAttributeSlug !== '' && $roleAttributeSlug !== 'user') {
            $mapped = self::forSlug($roleAttributeSlug);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        // 2) Fallback: role relation slug (hvis seeded / role_id findes)
        $legacyRole = $user->relationLoaded('role')
            ? $user->getRelation('role')
            : $user->role()->getResults();

        if ($legacyRole !== null) {
            $mapped = self::forSlug(is_string($legacyRole->slug) ? $legacyRole->slug : null);
            if ($mapped !== null) {
                return $mapped;
            }

            // 3) Sidste fallback: role->level hvis den findes
            if ($legacyRole->level !== null) {
                return (int) $legacyRole->level;
            }
        }

        // 4) Hvis der ingen role relation er, så brug users.role (inkl. 'user')
        if (is_string($roleAttributeSlug) && $roleAttributeSlug !== '') {
            $mapped = self::forSlug($roleAttributeSlug);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return self::LOWEST_LEVEL;
    }

    public static function atLeast(User $user, int $minimumLevel): bool
    {
        return self::levelForUser($user) >= $minimumLevel;
    }
}
