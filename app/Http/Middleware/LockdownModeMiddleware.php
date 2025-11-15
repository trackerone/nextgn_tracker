<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LockdownModeMiddleware
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security.lockdown', false)) {
            return $next($request);
        }

        $user = $request->user();
        $isAdmin = PermissionService::hasRole($user, 'admin');
        $routeIsLogin = $request->routeIs('login', 'login.*');
        $pathLooksLogin = $request->is('login', 'login/*');

        if ($routeIsLogin || $pathLooksLogin) {
            if ($user === null || $isAdmin) {
                return $next($request);
            }

            abort(503, 'Security lockdown active.');
        }

        if ($isAdmin) {
            return $next($request);
        }

        abort(503, 'Security lockdown active.');
    }
}
