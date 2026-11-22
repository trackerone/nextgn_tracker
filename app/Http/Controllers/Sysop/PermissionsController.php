<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\Logging\AuditLogger;
use App\Services\PermissionService;
use App\Services\Settings\RatioSettings;
use App\Services\Settings\RegistrationPolicy;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Tracker\UserRatioBalance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PermissionsController extends Controller
{
    public function index(Request $request, RatioSettings $ratioSettings, UserRatioBalance $balances, RegistrationPolicy $registration): View
    {
        $matrix = [];
        foreach (PermissionService::EDITABLE_ROLES as $role) {
            $matrix[$role] = PermissionService::permissionsForRole($role);
        }

        $identifier = trim((string) $request->query('user', ''));
        $target = $identifier === ''
            ? null
            : (ctype_digit($identifier)
                ? User::query()->find((int) $identifier)
                : User::query()->where('email', $identifier)->first());

        return view('sysop.permissions.index', [
            'roles' => PermissionService::EDITABLE_ROLES,
            'registrationMode' => $registration->mode(),
            'ratioValues' => [
                'tenure_enabled' => [
                    'value' => $ratioSettings->tenureEnabled(),
                    'overridden' => Setting::hasOverride('ratio.tenure_enabled'),
                ],
                'user_min_months' => [
                    'value' => $ratioSettings->userMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.user_min_months'),
                ],
                'power_user_min_months' => [
                    'value' => $ratioSettings->powerUserMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_months'),
                ],
                'elite_min_months' => [
                    'value' => $ratioSettings->eliteMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.elite_min_months'),
                ],
                'elite_min_ratio' => [
                    'value' => $ratioSettings->eliteMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.elite_min_ratio'),
                ],
                'power_user_min_ratio' => [
                    'value' => $ratioSettings->powerUserMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_ratio'),
                ],
                'power_user_min_downloaded' => [
                    'value' => $ratioSettings->powerUserMinDownloaded(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_downloaded'),
                ],
                'user_min_ratio' => [
                    'value' => $ratioSettings->userMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.user_min_ratio'),
                ],
            ],
            'creditDefaults' => $balances->registrationDefaults(),
            'creditIdentifier' => $identifier,
            'creditTarget' => $target,
            'creditBalance' => $target instanceof User ? $balances->totals($target) : null,
            'invites' => Invite::query()->with('inviter')->latest('created_at')
                ->paginate(12, ['*'], 'invite_page')->withQueryString(),
            'features' => PermissionService::MANAGED_FEATURES,
            'labels' => PermissionService::FEATURE_LABELS,
            'matrix' => $matrix,
            'overrides' => UserPermissionOverride::query()
                ->with('user:id,name,email')
                ->latest('updated_at')
                ->limit(40)
                ->get(),
        ]);
    }

    public function updateRole(
        Request $request,
        SiteSettingsRepository $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $rules = [
            'role' => ['required', Rule::in(PermissionService::EDITABLE_ROLES)],
            'audit_reason' => ['required', 'string', 'max:1000'],
            'permissions' => ['required', 'array:upload,forum,messages,invitations'],
        ];
        foreach (PermissionService::MANAGED_FEATURES as $feature => $permission) {
            $rules['permissions.'.$feature] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules);
        $submitted = $validated['permissions'];
        if (array_diff(array_keys($submitted), array_keys(PermissionService::MANAGED_FEATURES)) !== []) {
            throw ValidationException::withMessages(['permissions' => 'Unknown permission supplied.']);
        }

        $reason = trim((string) $validated['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        $role = (string) $validated['role'];
        $before = PermissionService::permissionsForRole($role);
        $after = [];
        foreach (PermissionService::MANAGED_FEATURES as $feature => $permission) {
            $after[$permission] = (bool) $submitted[$feature];
        }

        DB::transaction(function () use ($settings, $audit, $request, $role, $reason, $before, $after): void {
            $settings->set('access.roles.'.$role, $after, 'json');
            $audit->log('sysop.permissions.role_updated', null, [
                'actor_user_id' => $request->user()?->id,
                'role' => $role,
                'previous' => $before,
                'values' => $after,
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.permissions.index')->with('status', 'Role permissions saved. Changes take effect on the next request.');
    }

    public function updateUser(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'user_identifier' => ['required', 'string', 'max:255'],
            'feature' => ['required', Rule::in(array_keys(PermissionService::MANAGED_FEATURES))],
            'state' => ['required', Rule::in(['inherit', 'allow', 'deny'])],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);
        $reason = trim((string) $validated['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        $identifier = trim((string) $validated['user_identifier']);
        $target = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();

        if (! $target instanceof User) {
            throw ValidationException::withMessages(['user_identifier' => 'User not found. Enter an account ID or email address.']);
        }

        // No individual override may grant administrative powers or weaken sysop access.
        abort_if(PermissionService::roleForUser($target) === 'sysop', 403);

        $permission = PermissionService::MANAGED_FEATURES[$validated['feature']];
        $previous = UserPermissionOverride::query()
            ->where('user_id', $target->getKey())
            ->where('permission', $permission)
            ->first();
        $before = $previous?->allowed;
        $state = $validated['state'];

        DB::transaction(function () use ($target, $permission, $state, $audit, $request, $before, $reason): void {
            if ($state === 'inherit') {
                UserPermissionOverride::query()
                    ->where('user_id', $target->getKey())
                    ->where('permission', $permission)
                    ->delete();
            } else {
                UserPermissionOverride::query()->updateOrCreate(
                    ['user_id' => $target->getKey(), 'permission' => $permission],
                    ['allowed' => $state === 'allow'],
                );
            }

            $audit->log('sysop.permissions.user_updated', $target, [
                'actor_user_id' => $request->user()?->id,
                'target_user_id' => $target->getKey(),
                'permission' => $permission,
                'previous' => $before,
                'new_state' => $state,
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.permissions.index')->with('status', 'Individual permission updated.');
    }
}
