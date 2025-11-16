<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Roles\RoleLevel;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMinimumRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $minimumLevel): Response
    {
        $requiredLevel = (int) $minimumLevel;
        $user = $request->user();

        if ($user === null || ! RoleLevel::atLeast($user, $requiredLevel)) {
            throw new AuthorizationException('This action requires a higher role level.');
        }

        return $next($request);
    }
}
