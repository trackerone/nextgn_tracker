<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\UploadEligibilityEvent;
use App\Models\User;

final class UploadEligibilityTelemetryService
{
    public function record(User $user, UploadEligibilityDecision $decision): void
    {
        $payload = [
            'user_id' => (int) $user->getKey(),
            'allowed' => $decision->allowed,
            'reason' => $decision->reason?->value,
            'context' => $decision->context,
        ];

        UploadEligibilityEvent::query()->create($payload);

        logger()->info(
            $decision->allowed ? 'tracker.upload.allowed' : 'tracker.upload.denied',
            $payload,
        );
    }
}
