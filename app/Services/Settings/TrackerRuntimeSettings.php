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
        return $this->settings->getInt(self::ANNOUNCE_INTERVAL, (int) config('tracker.announce_interval_seconds', 1800));
    }

    public function ghostTimeout(): int
    {
        return $this->settings->getInt(self::GHOST_TIMEOUT, (int) config('tracker.ghost_peer_timeout_minutes', 45));
    }

    public function scrapeLimit(): int
    {
        return $this->settings->getInt(self::SCRAPE_LIMIT, (int) config('tracker.scrape_max_info_hashes', 50));
    }
}
