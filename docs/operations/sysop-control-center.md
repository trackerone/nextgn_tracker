# Sysop Control Center — operational rollout and limitations

Related: #599. The existing Log Center is at \`/sysop/logs\`; the health/incident overview is \`/sysop/operations\`. Only authenticated Sysop users may access incident actions and host probes.

## Required deployment

1. Run migrations: \`php artisan migrate --force\`.
2. Rebuild assets: \`npm ci && npm run build\`.
3. Clear the application cache: \`php artisan optimize:clear\`.
4. Ensure the real scheduler runs **every minute** (production cron or systemd timer), e.g. \`* * * * * cd /path/to/nextgn_tracker && /usr/bin/php artisan schedule:run >> /dev/null 2>&1\`.
5. Wait for an actual scheduler tick and an \`ops:monitor\` execution (every five minutes). Do not manually mark them healthy.
6. Configure \`APP_GIT_SHA\` at deploy for reliable release identification. Local checkouts can read the Git HEAD automatically.
7. Check the dashboard under a Sysop account and verify a member/admin receives 403 from Sysop endpoints.

To verify cron without changing data, check \`php artisan schedule:list\`. This merely lists scheduled definitions; it does **not** confirm execution. Use the recorded last-success times in Control Center.

## Counters and probe semantics

- /health is a public shallow **database + cache readiness** probe. Returns 200 with status ok only if dependencies respond; otherwise returns HTTP 503 with status degraded, with no exception detail or secrets.
- Sysop probes are **actual bounded operations**: SQL SELECT, cache put/get, storage temporary write/read and free space, scheduler heartbeat, monitored scheduled command results, and per-minute anonymous web and tracker counters.
- Healthy requires a successful probe. A missing collector reports Unknown; nonproduction config settings are not automatically failures. Sync queue means worker Not applicable, not worker healthy.
- Linux host metrics are read from bounded /proc files. The displayed system load average is **not** a CPU utilization percentage. Non-Linux hosts report Unknown instead of invented values.
- Tracker metrics use cache buckets for announce and scrape outcomes, not peer-identifying request logs. Never record passkeys, peer IPs, full URLs or arbitrary request bodies.
- Snapshot history is captured every five minutes and retained for 30 days. Its intervals summarize counters for the prior 15 minutes; the values overlap across successive snapshots.
- Direct log and incident inspection remains read-only; acknowledgements and resolution are audited as operational state changes, not shell commands.
- A failed scheduler command is distinguished from a completed command by its exit code where exposed by Laravel's scheduling event.

## Backup evidence (explicit, optional operator integration)

The site **does not create backups**. Integrate the backup command in the actual backup process **only after** the relevant archive has been written, safely stored and checksum verified.

Set \`OPS_BACKUP_ROOT=/var/backups/nextgn\` to an existing directory accessible to the operator process. Example, immediately after a successful encrypted database backup:

\`\`\`bash
sha256sum /var/backups/nextgn/database-latest.enc
php artisan ops:backup-evidence database /var/backups/nextgn/database-latest.enc --sha256=<expected-64-char-digest> --offsite
\`\`\`

Repeat for \`storage\` and \`secrets\`. The \`--offsite\` flag is an **operator attestation**, not independent inspection of a remote bucket.

After an isolated restore drill, save an audit report under the configured root and record:

\`\`\`bash
php artisan ops:backup-evidence restore /var/backups/nextgn/restore-report.txt --attest-restore
\`\`\`

A restore report is labeled **operator-attested** and is not independently repeated by NextGN. The health screen keeps missing/stale backup evidence and missing restore tests distinct. Backup files, paths, hashes and contents never appear on the web dashboard.

## Optional email for critical incidents

Notifications are disabled by default. To opt in, configure valid SMTP settings in a private environment/config store, test mail delivery, then enable:

\`\`\`dotenv
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
OPS_ALERT_EMAIL_ENABLED=true
OPS_ALERT_EMAIL=operator@example.org
\`\`\`

Send only generic incident type/label/timestamp. Duplicate critical incidents are suppressed for one hour; acknowledged incidents are not re-paged. Never copy raw logs or credentials into emails.

## External uptime is mandatory for true downtime alerts

A failed host cannot send its own alerts. Use an independent monitoring host/service to check the public \`GET /health\` endpoint every 60 seconds and alert after three consecutive failures. Optionally add an authenticated browser smoke test. Do not label uptime coverage Healthy until this external check has been configured and verified. No external vendor or recipient is provisioned by this PR.

## Maintenance and security

- Use a **minimum-privilege** scheduled PHP process. Do not give the web worker arbitrary shell access.
- Protect on-disk metrics, backups and mail credentials; these facilities are not exposed by routes.
- \`ops:monitor\` and \`ops:backup-evidence\` are CLI commands and do not execute remediation actions.
- Monitor disk free space and DB growth. Large deployments should replace file cache counters with Redis or another atomic shared counter store.
- Restore drills should be carried out monthly in isolated staging, and operator evidence reviewed independently.
- If no data is recorded, look for disabled/missing cron, permissions, migrations or cache-driver issues. An empty log is not evidence of zero failures.

## Planned extensions still in #599

- HTTP request-latency percentiles and longer historical charts; queue-worker heartbeat for asynchronous queue drivers; deployment/incident correlation; alert delivery channel retries and visibility.
- Centralized structured logs and request correlation IDs, redacted downloadable support bundles.
- Host memory/network history and capacity forecast; metadata/discovery/RSS pipeline health; accurate backup restore verification integration and external uptime confirmation.
- Operational advisories and safe diagnostic suggestions. Do not close #599 based on this slice alone.
