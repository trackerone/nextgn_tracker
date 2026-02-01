<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
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

        if ($user instanceof User) {
            $isBanned = $user->isBanned();
            $isDisabled = $user->isDisabled();
        } else {
            // Fallback for non-App\Models\User implementations / mocks.
            $isBanned = ((bool) ($user->is_banned ?? false))
                || (($user->banned_at ?? null) !== null)
                || (($user->status ?? null) === 'banned');

            $isDisabled = ((bool) ($user->is_disabled ?? false))
                || (($user->disabled_at ?? null) !== null)
                || (($user->status ?? null) === 'disabled')
                || (($user->status ?? null) === 'inactive');
        }

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
