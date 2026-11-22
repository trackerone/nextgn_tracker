<?php

declare(strict_types=1);

namespace App\Services\Settings;

final class TrackerRuntimeSettings
{
    public const ANNOUNCE_INTERVAL = 'tracker.runtime.announce_interval_seconds';

    public const GHOST_TIMEOUT = 'tracker.runtime.ghost_peer_timeout_minutes';

    public const SCRAPE_LIMIT = 'tracker.runtime.scrape_max_info_hashes';

    public function __construct(private readonly SiteSettingsRepository $settings) {}

    public function announceInterval(): int
    {
        return $this->settings->getInt(self::ANNOUNCE_INTERVAL);
    }

    public function ghostTimeout(): int
    {
        return $this->settings->getInt(self::GHOST_TIMEOUT);
    }

    public function scrapeLimit(): int
    {
        return $this->settings->getInt(self::SCRAPE_LIMIT);
    }
}
