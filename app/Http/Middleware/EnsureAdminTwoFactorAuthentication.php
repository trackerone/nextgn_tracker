<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Security\AdminTwoFactorSession;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.admin_two_factor.required', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            if ($request->routeIs('admin-two-factor.setup', 'admin-two-factor.enable', 'admin-two-factor.confirm')) {
                return $next($request);
            }

            return redirect()->route('admin-two-factor.setup');
        }

        if ($request->session()->has(AdminTwoFactorSession::VERIFIED_AT)) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->loginRedirect();
    }

    private function loginRedirect(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => 'Please sign in again and complete two-factor authentication.',
        ]);
    }
}
