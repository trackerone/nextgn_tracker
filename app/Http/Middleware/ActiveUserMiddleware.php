<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $next($request);
        }

        // Prefer model methods if they exist, otherwise fall back to common columns.
        $isBanned = method_exists($user, 'isBanned')
            ? (bool) $user->isBanned()
            : ((bool) ($user->is_banned ?? false))
                || (($user->banned_at ?? null) !== null)
                || (($user->status ?? null) === 'banned');

        $isDisabled = method_exists($user, 'isDisabled')
            ? (bool) $user->isDisabled()
            : ((bool) ($user->is_disabled ?? false))
                || (($user->disabled_at ?? null) !== null)
                || (($user->status ?? null) === 'disabled')
                || (($user->status ?? null) === 'inactive');

        if ($isBanned || $isDisabled) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403);
        }

        return $next($request);
    }
}
