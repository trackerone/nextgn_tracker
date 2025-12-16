<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRoleLevel
{
    public function handle(Request $request, Closure $next, string $level): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect('/login');
        }

        $role = (string) ($user->role ?? '');

        // Normalize expected input
        $level = strtolower(trim($level));

        // Levels:
        // - mod: moderator+admin+sysop
        // - admin: admin+sysop
        $allowed = match ($level) {
            'mod', 'moderator' => ['moderator', 'admin', 'sysop'],
            'admin' => ['admin', 'sysop'],
            default => [],
        };

        if ($allowed === [] || ! in_array($role, $allowed, true)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
