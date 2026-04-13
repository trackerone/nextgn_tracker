<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;
use App\Services\Torrents\UploadEligibility\Rules\DuplicateTorrentUploadEligibilityRule;
use App\Services\Torrents\UploadEligibility\Rules\MissingMetadataUploadEligibilityRule;
use App\Services\Torrents\UploadEligibility\Rules\UserRestrictionUploadEligibilityRule;

final class UploadEligibilityService
{
    /**
     * @var list<UserRestrictionUploadEligibilityRule|MissingMetadataUploadEligibilityRule|DuplicateTorrentUploadEligibilityRule>
     */
    private array $rules;

    public function __construct(
        private readonly UploadEligibilityTelemetryService $telemetry,
        UserRestrictionUploadEligibilityRule $userRestrictionRule,
        MissingMetadataUploadEligibilityRule $missingMetadataRule,
        DuplicateTorrentUploadEligibilityRule $duplicateTorrentRule,
    ) {
        $this->rules = [
            $userRestrictionRule,
            $missingMetadataRule,
            $duplicateTorrentRule,
        ];
    }

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

        foreach ($this->rules as $rule) {
            $reason = $rule->evaluate($context);
            if ($reason !== null) {
                return UploadEligibilityDecision::deny($reason, $decisionContext);
            }
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    private function record(User $user, UploadEligibilityDecision $decision): UploadEligibilityDecision
    {
        $this->telemetry->record($user, $decision);

        return $decision;
    }
}
