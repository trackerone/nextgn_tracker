<?php

declare(strict_types=1);

namespace App\Services\Settings;

final class RegistrationPolicy
{
    public const MODE_CLOSED = 'closed';

    public const MODE_INVITE = 'invite';

    public const MODE_OPEN = 'open';

    public const SETTING_KEY = 'registration.mode';

    public const MODES = [
        self::MODE_CLOSED,
        self::MODE_INVITE,
        self::MODE_OPEN,
    ];

    public function __construct(private readonly SiteSettingsRepository $settings) {}

    public function mode(): string
    {
        $mode = $this->settings->getString(self::SETTING_KEY);

        // Unknown or missing settings must not accidentally open signups.
        return in_array($mode, self::MODES, true) ? $mode : self::MODE_INVITE;
    }

    public function allowsRegistration(): bool
    {
        return $this->mode() !== self::MODE_CLOSED;
    }

    public function requiresInvite(): bool
    {
        return $this->mode() === self::MODE_INVITE;
    }
}
