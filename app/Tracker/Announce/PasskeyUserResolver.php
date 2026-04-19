<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Models\User;
use Illuminate\Http\Request;

final class PasskeyUserResolver
{
    public function __construct(private readonly AnnounceSecurityLogger $securityLogger) {}

    public function resolve(Request $request, string $passkey): User|AnnounceResult
    {
        $user = User::query()->where('passkey', $passkey)->first();

        if (! $user instanceof User) {
            $this->securityLogger->logInvalidPasskey($request, $passkey);

            return AnnounceResult::failure('Invalid passkey.');
        }

        if ($user->isBanned() || $user->isDisabled()) {
            $this->securityLogger->log(
                request: $request,
                user: $user,
                eventType: 'tracker.passkey_user_rejected',
                severity: 'medium',
                message: 'Tracker passkey rejected due to disabled or banned user state',
                context: [
                    'path' => $request->path(),
                    'is_banned' => $user->isBanned(),
                    'is_disabled' => $user->isDisabled(),
                ],
            );

            return AnnounceResult::failure('Invalid passkey.');
        }

        return $user;
    }
}
