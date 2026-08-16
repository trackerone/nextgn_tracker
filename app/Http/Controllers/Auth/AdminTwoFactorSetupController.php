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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

final class AdminTwoFactorSetupController extends Controller
{
    public function show(Request $request, EnableTwoFactorAuthentication $enable): View|RedirectResponse
    {
        if (! config('security.admin_two_factor.required', true)) {
            return redirect()->route('home');
        }

        $user = $this->admin($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return view('auth.admin-two-factor-setup', [
                'enabled' => true,
                'setupAuthorized' => false,
            ]);
        }

        $setupAuthorized = $this->setupIsAuthorized($request);

        if (! $setupAuthorized) {
            return view('auth.admin-two-factor-setup', [
                'enabled' => false,
                'setupAuthorized' => false,
            ]);
        }

        $enable($user);
        $user->refresh();

        return view('auth.admin-two-factor-setup', [
            'enabled' => false,
            'setupAuthorized' => true,
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'secretKey' => Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
            'recoveryCodes' => $user->recoveryCodes(),
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $user = $this->admin($request);
        $credentials = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($credentials['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password was incorrect.'],
            ]);
        }

        $request->session()->put(AdminTwoFactorSession::SETUP_AUTHORIZED_AT, now()->timestamp);
        $enable($user);

        return redirect()->route('admin-two-factor.setup');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $user = $this->admin($request);

        if (! $this->setupIsAuthorized($request)) {
            return redirect()->route('admin-two-factor.setup')->withErrors([
                'password' => 'Confirm your password before configuring two-factor authentication.',
            ]);
        }

        $credentials = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        try {
            $confirm($user, $credentials['code']);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => ['The authentication code was invalid.'],
            ]);
        }

        $request->session()->forget(AdminTwoFactorSession::SETUP_AUTHORIZED_AT);
        $request->session()->put(AdminTwoFactorSession::VERIFIED_AT, now()->timestamp);
        SecurityAuditLog::log($user, 'auth.admin_two_factor.enabled');

        return redirect()->route('admin-two-factor.setup')
            ->with('status', 'Two-factor authentication is now required for this administrator account.');
    }

    private function admin(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }

    private function setupIsAuthorized(Request $request): bool
    {
        $authorizedAt = $request->session()->get(AdminTwoFactorSession::SETUP_AUTHORIZED_AT);

        if (! is_int($authorizedAt)) {
            return false;
        }

        $ttl = (int) config('security.admin_two_factor.setup_authorization_ttl_seconds', 600);

        return $authorizedAt >= now()->subSeconds(max(1, $ttl))->timestamp;
    }
}
