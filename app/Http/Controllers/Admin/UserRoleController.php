<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use App\Services\Moderation\TorrentModerationDelegation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
        $this->middleware(['auth', 'staff', 'can:isAdmin']);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_if(! $actor instanceof User, 403);
        abort_if($actor->is($user), 403, 'Users cannot change their own role.');

        $data = $request->validate([
            'role' => ['required', Rule::in([
                User::ROLE_USER,
                User::ROLE_POWER_USER,
                User::ROLE_UPLOADER,
                User::ROLE_MODERATOR,
                User::ROLE_ADMIN,
                User::ROLE_SYSOP,
            ])],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $auditReason = trim((string) $data['audit_reason']);

        if ($auditReason === '') {
            throw ValidationException::withMessages([
                'audit_reason' => 'The audit reason field is required.',
            ]);
        }

        $oldRole = $user->resolveRoleIdentifier() ?? User::ROLE_USER;
        $newRole = (string) $data['role'];

        abort_if(
            ($oldRole === User::ROLE_SYSOP || $newRole === User::ROLE_SYSOP) && ! $actor->isSysop(),
            403,
            'Only sysops may assign or remove the sysop role.'
        );

        // Keep all persisted role representations in sync. Otherwise downgrading
        // to the default "user" can still inherit staff authority from role_id,
        // and an old is_staff flag can bypass staff middleware.
        $roleSlug = match ($newRole) {
            User::ROLE_USER => 'user1',
            User::ROLE_POWER_USER => 'user2',
            User::ROLE_UPLOADER => 'uploader1',
            User::ROLE_MODERATOR => 'mod1',
            User::ROLE_ADMIN => 'admin1',
            User::ROLE_SYSOP => 'sysop',
            default => throw new \InvalidArgumentException('Unsupported role transition.'),
        };

        $wasStaff = $user->isStaff();
        $willBeStaff = in_array($newRole, [User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SYSOP], true);

        DB::transaction(function () use ($user, $actor, $newRole, $oldRole, $roleSlug, $auditReason, $wasStaff, $willBeStaff): void {
            $user->forceFill([
                'role' => $newRole,
                'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
                'is_staff' => in_array($newRole, [
                    User::ROLE_MODERATOR,
                    User::ROLE_ADMIN,
                    User::ROLE_SYSOP,
                ], true),
            ])->save();

            $user->unsetRelation('role');

            // A staff demotion must never reactivate older individual queue
            // grants from before a promotion. Revoke in the same transaction.
            $revokedDelegations = 0;
            if ($wasStaff && ! $willBeStaff) {
                $revokedDelegations = DB::table('user_permission_overrides')
                    ->where('user_id', $user->getKey())
                    ->whereIn('permission', array_values(TorrentModerationDelegation::TASKS))
                    ->delete();
            }

            $this->auditLogger->log('user.role_changed', $user, [
                'actor_user_id' => $actor->id,
                'target_user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'revoked_moderation_delegations' => $revokedDelegations,
                'audit_reason' => $auditReason,
            ]);
        });

        return redirect()->back()->with('status', 'Role updated.');
    }
}
