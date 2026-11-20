<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserNameController extends Controller
{
    public function index(Request $request): View
    {
        $identifier = trim((string) $request->query('user', ''));
        $target = null;

        if ($identifier !== '') {
            $target = ctype_digit($identifier)
                ? User::query()->find((int) $identifier)
                : User::query()->where('email', $identifier)->first();
        }

        return view('admin.users.index', [
            'target' => $target,
            'identifier' => $identifier,
            'canRename' => $target instanceof User
                && ! $request->user()?->is($target)
                && ($request->user()?->isSysop() || ! $target->isAdmin()),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isAdmin(), 403);
        abort_if($actor->is($user), 403, 'You cannot rename your own account.');
        abort_if(! $actor->isSysop() && $user->isAdmin(), 403, 'Only Sysop can rename an administrator.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $name = trim((string) $data['name']);
        $reason = trim((string) $data['audit_reason']);

        if ($name === '' || $name === $user->name) {
            throw ValidationException::withMessages([
                'name' => 'Enter a different, non-empty username.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'audit_reason' => 'A reason for the requested username change is required.',
            ]);
        }

        $before = (string) $user->name;

        DB::transaction(function () use ($user, $name, $reason, $actor, $before, $audit): void {
            $user->forceFill(['name' => $name])->save();
            $audit->log('user.username_changed', $user, [
                'actor_user_id' => $actor->id,
                'target_user_id' => $user->id,
                'old_name' => $before,
                'new_name' => $name,
                'audit_reason' => $reason,
            ]);
        });

        // Public profile slug is deliberately preserved.
        return redirect()->route('admin.users.index', ['user' => $user->id])
            ->with('status', 'Username updated. The change is recorded in the audit log.');
    }
}
