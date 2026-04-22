<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Services\Settings\SiteSettingsRepository;

final class RatioRulesConfig
{
    public const ENFORCEMENT_ENABLED = 'tracker.ratio.enforcement_enabled';
    public const MINIMUM_DOWNLOAD_RATIO = 'tracker.ratio.minimum_download_ratio';
    public const FREELEECH_BYPASS_ENABLED = 'tracker.ratio.freeleech_bypass_enabled';
    public const NO_HISTORY_GRACE_ENABLED = 'tracker.ratio.no_history_grace_enabled';

    public function __construct(private readonly SiteSettingsRepository $settings) {
    }

    public function enforcementEnabled(): bool
    {
        return $this->settings->getBool(self::ENFORCEMENT_ENABLED);
    }

    public function minimumDownloadRatio(): float
    {
        return $this->settings->getFloat(self::MINIMUM_DOWNLOAD_RATIO);
    }

    public function freeleechBypassEnabled(): bool
    {
        return $this->settings->getBool(self::FREELEECH_BYPASS_ENABLED);
    }

    public function noHistoryGraceEnabled(): bool
    {
        return $this->settings->getBool(self::NO_HISTORY_GRACE_ENABLED);
    }
}
