<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
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

        // 1) Explicit staff flag (fast path)
        if ((bool) ($user->is_staff ?? false)) {
            return $next($request);
        }

        // 2) Normalized/legacy role attribute (tests often set this directly)
        $roleAttr = $user->getAttribute('role');
        if (is_string($roleAttr) && $roleAttr !== '') {
            $roleAttr = strtolower(trim($roleAttr));

            $staffRoleSlugs = [
                // Normalized roles
                User::ROLE_MODERATOR,
                User::ROLE_ADMIN,
                User::ROLE_SYSOP,

                // Legacy slugs
                'mod1',
                'mod2',
                'admin1',
                'admin2',
                'sysop',
            ];

            if (in_array($roleAttr, $staffRoleSlugs, true)) {
                return $next($request);
            }
        }

        // 3) Role relation / role_id (if present)
        $roleRelation = $user->getRelationValue('role');
        if ($roleRelation instanceof Role) {
            if ((bool) ($roleRelation->is_staff ?? false)) {
                return $next($request);
            }

            $level = $roleRelation->level;
            if ($level !== null && (int) $level >= Role::STAFF_LEVEL_THRESHOLD) {
                return $next($request);
            }

            $slug = $roleRelation->slug;
            if (is_string($slug) && $slug !== '') {
                $slug = strtolower($slug);

                if (in_array($slug, ['mod1', 'mod2', 'admin1', 'admin2', 'sysop'], true)) {
                    return $next($request);
                }
            }
        }

        // 4) Fallback to model method
        if ((bool) $user->isStaff()) {
            return $next($request);
        }

        $this->logDeniedStaffAccess($user, $request);
        abort(403, 'Staff only area.');
    }

    private function logDeniedStaffAccess(?User $user, Request $request): void
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (! str_starts_with($routeName, 'staff.torrents.')
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
