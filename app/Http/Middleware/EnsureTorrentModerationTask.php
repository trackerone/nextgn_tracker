<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Moderation\TorrentModerationDelegation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separate queue-task guard that retains denied-access security telemetry.
 * Policies in the controllers remain the final authorization on each torrent.
 */
final class EnsureTorrentModerationTask
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $task): Response
    {
        $user = $request->user();
        $allowed = $user instanceof User && match ($task) {
            'view' => TorrentModerationDelegation::canView($user),
            'approve' => TorrentModerationDelegation::canApprove($user),
            'reject' => TorrentModerationDelegation::canReject($user),
            default => false,
        };

        if (! $allowed) {
            $torrent = $request->route('torrent');

            SecurityAuditLog::logAndWarn($user instanceof User ? $user : null, 'torrent.moderation.unauthorized', [
                'route' => (string) ($request->route()?->getName() ?? ''),
                'torrent' => $torrent instanceof Torrent
                    ? (string) $torrent->getKey()
                    : (is_scalar($torrent) ? (string) $torrent : null),
            ]);

            abort(403, 'Moderation task not granted.');
        }

        return $next($request);
    }
}
