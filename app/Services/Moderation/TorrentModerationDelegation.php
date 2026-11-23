<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Delegation is deliberately separate from editable member-feature permissions.
 * Existing staff roles retain their moderation powers; non-staff members can
 * receive only the listed queue tasks, never general staff/admin access.
 */
final class TorrentModerationDelegation
{
    /** @var array<string, string> */
    public const TASKS = [
        'view' => 'moderation.torrents.view',
        'approve' => 'moderation.torrents.approve',
        'reject' => 'moderation.torrents.reject',
    ];

    public static function canView(User $user): bool
    {
        if (! self::isActive($user)) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        // A delegated decision automatically grants access to the queue in
        // which it can be performed, even without an additional view grant.
        return DB::table('user_permission_overrides')
            ->where('user_id', $user->getKey())
            ->whereIn('permission', array_values(self::TASKS))
            ->where('allowed', true)
            ->exists();
    }

    public static function canApprove(User $user): bool
    {
        return self::hasTask($user, self::TASKS['approve']);
    }

    public static function canReject(User $user): bool
    {
        return self::hasTask($user, self::TASKS['reject']);
    }

    private static function hasTask(User $user, string $permission): bool
    {
        if (! self::isActive($user)) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        // Read the database on every request; revocations must not outlive
        // a cache/session and false overrides cannot grant a task.
        return DB::table('user_permission_overrides')
            ->where('user_id', $user->getKey())
            ->where('permission', $permission)
            ->where('allowed', true)
            ->exists();
    }

    private static function isActive(User $user): bool
    {
        return ! $user->isBanned() && ! $user->isDisabled();
    }
}
