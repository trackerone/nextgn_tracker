<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;

final class UploadEligibilityService
{
    public function __construct(
        private readonly UploadEligibilityTelemetryService $telemetry,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function canUpload(User $user, array $context = []): bool
    {
        return $this->decide($user, $context)->allowed;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(User $user, array $context = []): UploadEligibilityDecision
    {
        return $this->record($user, $this->decide($user, $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function decide(User $user, array $context = []): UploadEligibilityDecision
    {
        $decisionContext = $this->buildDecisionContext($user, $context);

        if ($user->isBanned()) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserBanned, $decisionContext);
        }

        if ($user->isDisabled()) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserDisabled, $decisionContext);
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildDecisionContext(User $user, array $context): array
    {
        return array_filter([
            'category' => $this->asStringOrNull($context['category'] ?? null),
            'type' => $this->asStringOrNull($context['type'] ?? null),
            'resolution' => $this->asStringOrNull($context['resolution'] ?? null),
            'scene' => $this->asBoolOrNull($context['scene'] ?? null),
            'duplicate' => $this->asBoolOrNull($context['duplicate'] ?? null),
            'size' => $this->asIntOrNull($context['size'] ?? null),
            'is_banned' => $user->isBanned(),
            'is_disabled' => $user->isDisabled(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function asStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function asBoolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function asIntOrNull(mixed $value): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return $value;
    }

    private function record(User $user, UploadEligibilityDecision $decision): UploadEligibilityDecision
    {
        $this->telemetry->record($user, $decision);

        return $decision;
    }
}
