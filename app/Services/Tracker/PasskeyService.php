<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PasskeyService
{
    public function generate(User $user): string
    {
        $passkey = $this->uniquePasskey();

        $user->forceFill(['passkey' => $passkey])->save();

        return $passkey;
    }

    public function rotate(User $user): string
    {
        $oldPasskey = (string) $user->passkey;
        $passkey = $this->generate($user);

        Log::info('Tracker passkey rotated.', [
            'user_id' => $user->getKey(),
            'old_passkey_suffix' => $oldPasskey !== '' ? substr($oldPasskey, -6) : null,
        ]);

        return $passkey;
    }

    private function uniquePasskey(): string
    {
        do {
            $candidate = bin2hex(random_bytes(32));
        } while (User::query()->where('passkey', $candidate)->exists());

        return $candidate;
    }
}
