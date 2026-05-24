<?php

declare(strict_types=1);

return [
    [
        'key' => 'metadata.refresh',
        'label' => 'Metadata Refresh',
        'description' => 'Refreshes external metadata enrichment for recently active torrents.',
        'category' => 'metadata refresh',
        'critical' => false,
        'sysop_controllable' => true,
        'default_enabled' => true,
    ],
    [
        'key' => 'cache.warm',
        'label' => 'Cache Warmup',
        'description' => 'Warms low-risk UI and listing cache layers used by non-critical pages.',
        'category' => 'cache warming',
        'critical' => false,
        'sysop_controllable' => true,
        'default_enabled' => true,
    ],
    [
        'key' => 'notification.digest',
        'label' => 'Notification Digest',
        'description' => 'Builds summary digest notifications for users.',
        'category' => 'notification digest',
        'critical' => false,
        'sysop_controllable' => true,
        'default_enabled' => true,
    ],
    [
        'key' => 'health.snapshot',
        'label' => 'Health Snapshot',
        'description' => 'Captures low-impact operational health snapshots for dashboard visibility.',
        'category' => 'health snapshot jobs',
        'critical' => false,
        'sysop_controllable' => true,
        'default_enabled' => true,
    ],
    [
        'key' => 'tracker.announce.integrity',
        'label' => 'Announce Integrity Enforcement',
        'description' => 'Critical tracker announce integrity safeguards are server-controlled.',
        'category' => 'announce integrity',
        'critical' => true,
        'sysop_controllable' => false,
        'default_enabled' => true,
    ],
    [
        'key' => 'tracker.ratio.accounting',
        'label' => 'Ratio Accounting',
        'description' => 'Critical ratio/stat accounting task is immutable and managed by server runtime.',
        'category' => 'ratio/stat accounting',
        'critical' => true,
        'sysop_controllable' => false,
        'default_enabled' => true,
    ],
];
