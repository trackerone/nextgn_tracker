<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsStaff
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            $this->logDeniedStaffAccess(null, $request);
            abort(403, 'Staff only area.');
        }

        // Reuse the same explicit staff-role decision as policies and queries.
        // Arbitrary high numeric legacy levels are never a staff credential.
        if ($user->isStaff()) {
            return $next($request);
        }

        $this->logDeniedStaffAccess($user, $request);
        abort(403, 'Staff only area.');
    }

    private function logDeniedStaffAccess(?User $user, Request $request): void
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (
            ! str_starts_with($routeName, 'staff.torrents.')
            && ! str_starts_with($routeName, 'api.moderation.uploads.')
            && $routeName !== 'moderation.uploads'
        ) {
            return;
        }

        SecurityAuditLog::logAndWarn($user, 'torrent.moderation.unauthorized', [
            'route' => $routeName,
            'torrent' => is_scalar($request->route('torrent')) ? (string) $request->route('torrent') : null,
        ]);
    }
}
