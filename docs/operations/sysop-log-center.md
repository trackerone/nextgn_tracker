# Sysop Log Center — P0a log visibility

## Goal

Give a Sysop a safe, mobile-usable, read-only way to investigate ordinary tracker failures without SSH, while reusing NextGN's existing audit, security and failed-job records.

## Access

- Entry: user menu **System logs**, or **Sysop operations → Open Log Center**.
- Route: GET /sysop/logs (sysop.logs.index).
- Authenticated Sysop only, within the existing Sysop middleware and admin throttle.
- Existing /admin/logs/audit and /admin/logs/security retain their current role gates; their detail views also redact structured secrets.

## Data sources

| Tab | Source | Notes |
| --- | --- | --- |
| Application | Local storage/logs/laravel.log and laravel-YYYY-MM-DD.log | Read-only, 4 newest eligible files, last 512 KiB/file, max 800 parsed matches, 75 displayed. Multiline stack traces deliberately excluded. This tab does NOT read stdout/stderr, syslog, container logs, or third-party collectors. |
| Audit | Existing audit_logs model | Paginated 30 per page; source event details remain in the original audit view. |
| Security | Existing security_events model | Paginated 30 per page, with severity mapping low/info, medium/warning, high/error, critical/critical. |
| Failed jobs | Existing failed_jobs database table | Paginated 30 per page; never expose job payloads; show a sanitized single-line exception summary. |

Time windows: 1 hour, 24 hours, 7 days, 30 days. Text search and severity filters are available. Source selection is allowlisted; no file name or filesystem path is accepted as an input.

## Privacy and operational safety

- SensitiveLogRedactor masks named secret keys in structured payloads and standard token/password patterns in messages; never expose Laravel stack traces as-is.
- **Redaction is defense-in-depth, not a guarantee for arbitrarily formatted secrets.** Log producers should avoid recording raw request bodies, auth headers, tracker passkeys, passwords, session IDs and complete announce URLs in the first place.
- Never use this page for remote shell commands, log file deletion, queue retries, changes to retention, or privilege escalation.
- Logging may reside outside the local Laravel files (e.g. container stdout). If so, this tab is incomplete. Integrate a secure central log backend separately rather than pretending a blank table means health.
- Log view doesn't prove that queue, scheduler, tracker or backup jobs are running. Use separate heartbeat/metrics instrumentation in follow-up PRs (#599).

## Performance and next steps

- Reading large Laravel logs is bounded so a Sysop page request cannot scan unbounded multi-GB files. The Application tab is a *recent tail*, not a complete archive; 7d/30d filters cannot recover overwritten/rotated data.
- Audit/security/failed-job pagination relies on database indexes and conventional retention; review query plans as event volume grows.
- Introduce structured correlation IDs, consistent event levels, source-specific retention, central log ingestion and full-text search before claiming completeness at production scale.
- Next planned work: per-job scheduler heartbeats, announce/scrape success/failure counters, public shallow vs private deep health checks, container/systemd telemetry, alert acknowledgements and external uptime monitoring.

## Verification checklist

- Guest → login; member/moderator/admin → 403; Sysop → 200.
- Filters and pagination work, and failed jobs display no serialized payloads.
- Existing audit/security detail pages mask sensitive keys.
- Sample logs with tracker URL passkey, bearer token and query token are sanitized.
- No user-supplied file paths or unrestricted file reads.
- Missing log files and unavailable database sources produce a safe empty/degraded view.
- Follow-up product acceptance: verify mobile rendering on the Debian test site and confirm logs are received from the actual configured production logging driver.
