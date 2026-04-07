<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

final class AnnounceSecurityLogger
{
    public function logInvalidPasskey(Request $request, string $passkey): void
    {
        $this->log(
            request: $request,
            user: null,
            eventType: 'tracker.invalid_passkey',
            severity: 'low',
            message: 'Invalid passkey used during announce attempt',
            context: [
                'passkey' => $passkey,
                'path' => $request->path(),
                'query' => $request->query(),
                'headers' => [
                    'user-agent' => $request->userAgent(),
                ],
            ],
        );
    }

    public function log(
        Request $request,
        ?User $user,
        string $eventType,
        string $severity,
        string $message,
        array $context,
    ): void {
        try {
            SecurityEvent::query()->create([
                'user_id' => $user?->getKey(),
                'ip_address' => (string) ($request->ip() ?? '0.0.0.0'),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'event_type' => $eventType,
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Throwable) {
            // Never break announce flow because of logging.
        }
    }
}
