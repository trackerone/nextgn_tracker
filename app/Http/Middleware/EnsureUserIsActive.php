<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Logging\SecurityEventLogger;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private readonly SecurityEventLogger $securityLogger)
    {
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ($user->isBanned() || $user->isDisabled())) {
            $this->securityLogger->log('auth.blocked_user', 'medium', 'Blocked banned/disabled user from accessing app.', [
                'user_id' => $user->getKey(),
                'reason' => $user->isBanned() ? 'banned' : 'disabled',
            ]);

            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Your account is disabled.');
        }

        return $next($request);
    }

    private function guard(): Guard
    {
        return Auth::guard();
    }
}
