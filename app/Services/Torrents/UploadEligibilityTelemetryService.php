<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\UploadEligibilityEvent;
use App\Models\User;

final class UploadEligibilityTelemetryService
{
    public function record(User $user, UploadEligibilityDecision $decision): void
    {
        $context = $decision->context;
        // Raw NFO belongs in NFO storage, not in every eligibility log entry.
        if (is_array($context['extracted_metadata'] ?? null)) {
            unset($context['extracted_metadata']['raw_nfo']);
        }

        $payload = [
            'user_id' => (int) $user->getKey(),
            'allowed' => $decision->allowed,
            'reason' => $decision->reason?->value,
            'context' => $context,
        ];

        UploadEligibilityEvent::query()->create($payload);

        logger()->info(
            $decision->allowed ? 'tracker.upload.allowed' : 'tracker.upload.denied',
            $payload,
        );
    }
}
