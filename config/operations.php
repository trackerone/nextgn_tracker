<?php

declare(strict_types=1);

return [
    // Only used by the CLI backup evidence command. No path is exposed via HTTP.
    'backup_root' => env('OPS_BACKUP_ROOT', ''),
    // Disabled by default; enable only after mail delivery and contact consent are configured.
    'notifications_enabled' => (bool) env('OPS_ALERT_EMAIL_ENABLED', false),
    'notification_email' => env('OPS_ALERT_EMAIL', ''),
];
