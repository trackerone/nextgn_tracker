<?php

declare(strict_types=1);

namespace App\Services\Torrents;

enum UploadEligibilityReason: string
{
    case DuplicateTorrent = 'duplicate_torrent';
    case RatioTooLow = 'ratio_too_low';
    case CategoryBlocked = 'category_blocked';
    case ResolutionBlocked = 'resolution_blocked';
    case ScenePolicyRejected = 'scene_policy_rejected';
    case MissingMetadata = 'missing_metadata';
    case StaffOnlyCategory = 'staff_only_category';
    case AutoUploadRejected = 'autoupload_rejected';
    case UserBanned = 'user_banned';
    case UserDisabled = 'user_disabled';
}
