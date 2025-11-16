<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
        $this->middleware(['auth', 'staff', 'can:isAdmin']);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([
                User::ROLE_USER,
                User::ROLE_POWER_USER,
                User::ROLE_UPLOADER,
                User::ROLE_MODERATOR,
                User::ROLE_ADMIN,
                User::ROLE_SYSOP,
            ])],
        ]);

        $oldRole = $user->getAttribute('role');
        $user->forceFill(['role' => $data['role']])->save();

        $this->auditLogger->log('user.role_changed', $user, [
            'old_role' => $oldRole,
            'new_role' => $data['role'],
            'changed_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with('status', 'Role updated.');
    }
}
