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

        if ($user instanceof User) {
            return $user;
        }

        $this->securityLogger->logInvalidPasskey($request, $passkey);

        return AnnounceResult::failure('Invalid passkey.');
    }
}
