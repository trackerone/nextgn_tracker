# Sysop Log Center — P0a log visibility

## Goal

Give a Sysop a safe, mobile-usable, read-only way to investigate ordinary tracker failures without SSH, while reusing NextGN's existing audit, security and failed-job records.

## Access

- Entry: user menu **System logs**, or **Sysop operations → Open Log Center**.
- Route: GET /sysop/logs (sysop.logs.index).
- Authenticated Sysop only, within the existing Sysop middleware and admin throttle.
- Existing /admin/logs/audit and /admin/logs/security retain their current role gates; their detail views also redact structured secrets.

## Log Center UX (November 2026)

- Default landing view is **Overview**, not an arbitrary log source. It shows recorded counts (security, audit, failed jobs, incidents) for the selected window and a short latest-high-priority list drawn from security high/critical, ops incidents and failed jobs. These are not comprehensive monitoring totals; unavailable counters show **—**, never zero.
- Six tabs: Overview, Application, Security, Audit trail, Failed jobs, Incidents. **Tracker** and **Infrastructure** are component filters within Incidents because they all read the same `ops_alerts` dataset. Existing direct `source=tracker` and `source=infrastructure` links remain accepted.
- Readable desktop table and compact mobile event cards replace tall, repeated event blocks. Security entries display their actual low/medium/high/critical severity rather than mapping the display label to generic Laravel levels.
- Search, timeframe, severity and pagination are included in Security/Audit detail links. Returning to Log Center restores those filters; desktop records include a focus anchor. Detailed raw context is behind an accessible disclosure control and stays redacted.
- Every source explains its actual coverage next to the results. Application shows the current logging driver and warns that the local-file tail is not stdout/stderr monitoring. Blank results do not imply healthy systems.
- Source-count cards and high-priority events are observational UI, not new telemetry or alerting. This change does not alter logger configuration, retention, ingestion, alert policy or incident processing.

## Data sources

| Tab | Source | Notes |
| --- | --- | --- |
| Overview | Recorded counts and bounded latest-priority entries | Security high/critical, ops incidents and failed jobs; audit counted but not part of priority list; application not counted. Missing sources show unknown. |
| Incidents | Existing `ops_alerts` | Component filter for tracker vs infrastructure; these are recorded incidents rather than raw signals. |
| Application | Local storage/logs/laravel.log and laravel-YYYY-MM-DD.log | Read-only, 4 newest eligible files, last 512 KiB/file, max 800 parsed matches, 75 displayed. Multiline stack traces deliberately excluded. This tab does NOT read stdout/stderr, syslog, container logs, or third-party collectors. |
| Audit | Existing audit_logs model | Paginated 30 per page; source event details remain in the original audit view. |
| Security | Existing security_events model | Paginated 30 per page, with severity mapping low/info, medium/warning, high/error, critical/critical. |
| Failed jobs | Existing failed_jobs database table | Paginated 30 per page; never expose job payloads; show a sanitized single-line exception summary. |

Time windows: 15 minutes, 1 hour, 24 hours, 7 days, 30 days. Text search and severity filters are available. Source selection is allowlisted; no file name or filesystem path is accepted as an input.

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
- Overview starts by default, incident source filters work, and missing source counts are not misrepresented as zero.
- Filters and pagination are preserved on return from detail pages, and failed jobs display no serialized payloads.
- Existing audit/security detail pages mask sensitive keys.
- Sample logs with tracker URL passkey, bearer token and query token are sanitized.
- No user-supplied file paths or unrestricted file reads.
- Missing log files and unavailable database sources produce a safe empty/degraded view.
- Follow-up product acceptance: verify mobile rendering on the Debian test site and confirm logs are received from the actual configured production logging driver.
