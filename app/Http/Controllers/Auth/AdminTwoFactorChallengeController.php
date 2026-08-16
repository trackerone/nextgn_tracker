<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Support\Security\AdminTwoFactorSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class AdminTwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($this->challengedAdmin($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.admin-two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $user = $this->challengedAdmin($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $credentials = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/^[0-9]{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:64'],
        ]);

        $validCode = false;
        $usedRecoveryCode = null;

        if (is_string($credentials['code'] ?? null)) {
            $validCode = $provider->verify(
                Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
                $credentials['code'],
            );
        } elseif (is_string($credentials['recovery_code'] ?? null)) {
            $usedRecoveryCode = collect($user->recoveryCodes())->first(
                static fn (mixed $code): bool => is_string($code)
                    && hash_equals($code, $credentials['recovery_code'])
            );
            $validCode = is_string($usedRecoveryCode);
        }

        if (! $validCode) {
            SecurityAuditLog::logAndWarn($user, 'auth.admin_two_factor.failed');

            throw ValidationException::withMessages([
                'code' => ['The authentication or recovery code was invalid.'],
            ]);
        }

        if (is_string($usedRecoveryCode)) {
            $user->replaceRecoveryCode($usedRecoveryCode);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(AdminTwoFactorSession::PENDING_USER_ID);
        $request->session()->put(AdminTwoFactorSession::VERIFIED_AT, now()->timestamp);
        SecurityAuditLog::log($user, 'auth.admin_two_factor.passed', [
            'recovery_code' => is_string($usedRecoveryCode),
        ]);

        return redirect()->intended('/home');
    }

    private function challengedAdmin(Request $request): ?User
    {
        $userId = $request->session()->get(AdminTwoFactorSession::PENDING_USER_ID);

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return null;
        }

        $user = User::query()->find((int) $userId);

        if (! $user instanceof User
            || ! $user->isAdmin()
            || ! $user->hasEnabledTwoFactorAuthentication()
            || $user->isBanned()
            || $user->isDisabled()
        ) {
            $request->session()->forget(AdminTwoFactorSession::PENDING_USER_ID);

            return null;
        }

        return $user;
    }
}
