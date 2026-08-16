<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\Security\AdminTwoFactorSession;
use App\Support\Security\LoginThrottleKey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        foreach (LoginThrottleKey::keysForClearingRequest($request) as $key) {
            RateLimiter::clear($key);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (config('security.admin_two_factor.required', true)
            && $user instanceof User
            && $user->isAdmin()
        ) {
            if ($user->hasEnabledTwoFactorAuthentication()) {
                $userId = (int) $user->getAuthIdentifier();

                Auth::logout();
                $request->session()->put(AdminTwoFactorSession::PENDING_USER_ID, $userId);

                return redirect()->route('admin-two-factor.challenge');
            }

            $request->session()->put(AdminTwoFactorSession::SETUP_AUTHORIZED_AT, now()->timestamp);

            return redirect()->route('admin-two-factor.setup');
        }

        return redirect('/home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
