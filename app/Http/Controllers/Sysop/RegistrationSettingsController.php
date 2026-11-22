<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\RegistrationPolicy;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RegistrationSettingsController extends Controller
{
    public function update(
        Request $request,
        RegistrationPolicy $registration,
        SiteSettingsRepository $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'registration_mode' => ['required', Rule::in(RegistrationPolicy::MODES)],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $validated['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        $before = $registration->mode();
        $after = (string) $validated['registration_mode'];

        DB::transaction(function () use ($settings, $audit, $request, $before, $after, $reason): void {
            $settings->set(RegistrationPolicy::SETTING_KEY, $after, 'string');
            $audit->log('sysop.registration.mode_updated', null, [
                'actor_user_id' => $request->user()?->getKey(),
                'previous' => $before,
                'mode' => $after,
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->to(route('sysop.permissions.index', ['section' => 'invitations']).'#invitations')
            ->with('status', 'Registration policy updated. The new mode applies immediately.');
    }
}
