<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $user->forceFill(['role' => $newRole])->save();

        $this->auditLogger->log('user.role_changed', $user, [
            'actor_user_id' => $actor->id,
            'target_user_id' => $user->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'audit_reason' => $auditReason,
        ]);

        return redirect()->back()->with('status', 'Role updated.');
    }
}
