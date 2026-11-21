<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Tracker\UserRatioBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RatioCreditController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $identifier = trim((string) $request->query('user', ''));
        $query = $identifier !== '' ? ['user' => $identifier] : [];

        return redirect()->to(route('sysop.permissions.index', $query).'#balances');
    }

    public function updateDefaults(
        Request $request,
        SiteSettingsRepository $settings,
        UserRatioBalance $balances,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'uploaded_gib' => ['required', 'numeric', 'min:0', 'max:10000'],
            'downloaded_gib' => ['required', 'numeric', 'min:0', 'max:10000'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $data['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        $before = $balances->registrationDefaults();
        $after = [
            'uploaded' => (float) $data['uploaded_gib'],
            'downloaded' => (float) $data['downloaded_gib'],
        ];

        DB::transaction(function () use ($request, $settings, $audit, $before, $after, $reason): void {
            $settings->set(UserRatioBalance::DEFAULT_UPLOADED_GIB, $after['uploaded'], 'float');
            $settings->set(UserRatioBalance::DEFAULT_DOWNLOADED_GIB, $after['downloaded'], 'float');
            $audit->log('sysop.ratio.defaults_updated', null, [
                'actor_user_id' => $request->user()?->getKey(),
                'previous_defaults_gib' => $before,
                'new_defaults_gib' => $after,
                'reason' => $reason,
            ]);
        });

        return redirect()->to(route('sysop.permissions.index').'#balances')
            ->with('status', 'Starting credits updated for future registrations only.');
    }

    public function adjust(
        Request $request,
        User $user,
        UserRatioBalance $balances,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'metric' => ['required', Rule::in(['uploaded', 'downloaded'])],
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount_gib' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $data['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        $balances->adjust(
            $actor,
            $user,
            $data['metric'],
            $data['direction'],
            (float) $data['amount_gib'],
            $reason,
            $audit,
        );

        return redirect()->to(route('sysop.permissions.index', ['user' => $user->id]).'#balances')
            ->with('status', 'Ratio credit adjustment saved and audited.');
    }
}
