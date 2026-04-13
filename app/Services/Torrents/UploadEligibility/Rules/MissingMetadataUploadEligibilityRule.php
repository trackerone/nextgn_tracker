<?php

declare(strict_types=1);

namespace App\Services\Torrents\UploadEligibility\Rules;

use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadPreflightContext;

final class MissingMetadataUploadEligibilityRule
{
    public function evaluate(UploadPreflightContext $context): ?UploadEligibilityReason
    {
        if ($context->metadataComplete === false) {
            return UploadEligibilityReason::MissingMetadata;
        }

        return null;
    }
}
