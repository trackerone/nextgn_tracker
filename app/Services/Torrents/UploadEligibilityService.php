<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;

final class UploadEligibilityService
{
    public function __construct(
        private readonly UploadEligibilityTelemetryService $telemetry,
    ) {}

    public function canUpload(UploadPreflightContext $context): bool
    {
        return $this->decide($context)->allowed;
    }

    public function evaluate(User $user, UploadPreflightContext $context): UploadEligibilityDecision
    {
        return $this->record($user, $this->decide($context));
    }

    public function decide(UploadPreflightContext $context): UploadEligibilityDecision
    {
        $decisionContext = $context->toArray();

        $userDecision = $this->applyUserRestrictions($context, $decisionContext);
        if (! $userDecision->allowed) {
            return $userDecision;
        }

        if ($context->metadataComplete === false) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::MissingMetadata, $decisionContext);
        }

        if ($context->duplicate === true) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::DuplicateTorrent, $decisionContext);
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    /**
     * @param  array<string, mixed>  $decisionContext
     */
    private function applyUserRestrictions(UploadPreflightContext $context, array $decisionContext): UploadEligibilityDecision
    {
        if ($context->isBanned) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserBanned, $decisionContext);
        }

        if ($context->isDisabled) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserDisabled, $decisionContext);
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    private function record(User $user, UploadEligibilityDecision $decision): UploadEligibilityDecision
    {
        $this->telemetry->record($user, $decision);

        return $decision;
    }
}
