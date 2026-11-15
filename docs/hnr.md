# HnR policy and operations

HnR is opt-in at `/sysop/hnr`, linked from Site settings. Installation leaves it disabled. The provisional seed target is 72 hours; sysop must choose appropriate values before enabling. Defaults are a per-torrent ratio of 1.0 OR completed-content seed time, 120 inactive hours, at least 40 overdue torrents, one warning per 31 days, one warning expiring per 62 warning-free days, and three warnings before a later eligible review restricts new downloads. Staff/uploader role exemptions are configurable. There is no VIP role assumption.

## Accounting

- New obligations begin only on positive, integrity-validated physical download deltas after activation. There is no historical backfill and fetching a `.torrent` alone creates no obligation.
- Freeleech counts physical downloaded bytes for HnR even though the ratio-credit calculator may assign zero global download credit.
- Partial downloads owe only actual downloaded bytes. They may fulfill the per-torrent ratio requirement, but seed-time fulfillment requires full completion.
- Seed time requires a previously seeding peer with a fresh announce and a new seeding report. Offline gaps and suspicious reports receive no time credit. One per-user/torrent watermark prevents simultaneous clients from doubling elapsed time. Zero-transfer heartbeats refresh the peer and contribute valid seed time.
- Seed-time and ratio requirements are snapshotted on creation. Later policy changes do not increase existing obligations. An explicit staff waiver persists through subsequent announces.
- Deleted, hidden or banned torrents do not trigger warnings. Active clients and obligations still within the grace period are excluded.

## Warnings and access

Run `php artisan hnr:evaluate` or use the registered hourly Laravel schedule. The HP scheduler still needs to invoke Laravel's scheduler; a schedule definition is not proof of execution.

Evaluation is transactional and locks the user before changing warning state. Repeated/concurrent evaluations cannot issue another warning within the cooldown. Warnings and actions appear in `/account/hnr`; the account overview shows the warning count. Expiry is counted in configured days, not ambiguous calendar months.

A later review after the configured number of warnings can restrict new `.torrent` downloads through web, API and RSS policy checks, and new downloading announces. Existing obligated torrents remain available for recovery, including partial downloads. Seeding announces remain available. Resuming sufficient obligations restores access on the next review. This restriction does not change the user's role or permanently disable their account.

Disabling HnR suspends tracking and enforcement. Re-enabling restarts inactivity grace and warning cooldown for outstanding records; dormant enforcement is not applied as an immediate burst of warnings. Existing warning/restriction records remain visible.

Sysop can waive a single obligation or reset warnings/restriction with a required reason. Actions are audited; resetting warnings does not remove seed obligations. Resetting also grants the configured warning cooldown.

## Scope

This implements seeding obligations, rules, warning progression/expiry, reversible new-download restrictions, private user status and audited staff review. It does not implement bonus points, upload-credit spending, paid VIP exemptions, donation-based release, automatic permanent account disablement, or automatic invitation revocation. No IPT commercial policy is copied implicitly.

Deployment requires the additive HnR migration. No historic torrents are charged merely by running the migration.
