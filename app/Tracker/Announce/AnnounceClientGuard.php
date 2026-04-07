<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Models\User;
use Illuminate\Http\Request;

final class AnnounceClientGuard
{
    public function __construct(private readonly AnnounceSecurityLogger $securityLogger) {}

    public function ensureAllowed(Request $request, User $user): ?AnnounceResult
    {
        $userAgent = (string) ($request->userAgent() ?? '');
        if ($userAgent === '' || ! str_contains($userAgent, 'BannedClient')) {
            return null;
        }

        $this->securityLogger->log(
            request: $request,
            user: $user,
            eventType: 'tracker.client_banned',
            severity: 'high',
            message: 'Banned client attempted announce',
            context: [
                'user_agent' => $userAgent,
                'path' => $request->path(),
            ],
        );

        return AnnounceResult::failure('Client is banned.');
    }
}
