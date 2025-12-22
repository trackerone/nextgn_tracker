<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Roles\RoleLevel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRoleLevel
{
    /**
     * Usage:
     *  - role.level:mod   => requires >= 8
     *  - role.level:admin => requires >= 10
     *  - role.level:8     => requires >= 8 (numeric passthrough)
     */
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response('Forbidden', 403);
        }

        $requiredLevel = $this->requiredLevelFromToken($required);
        $userLevel = RoleLevel::levelForUser($user);

        if ($userLevel < $requiredLevel) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }

    private function requiredLevelFromToken(string $token): int
    {
        $token = strtolower(trim($token));

        if (is_numeric($token)) {
            return (int) $token;
        }

        return match ($token) {
            'mod', 'moderator' => 8,
            'admin' => 10,
            'sysop' => 12,
            default => 10, // fail-closed: unknown tokens treated as admin-level
        };
    }
}
