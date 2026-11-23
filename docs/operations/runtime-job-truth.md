# Runtime job execution truth in Sysop Operations

The optional runtime job registry separates **stored settings** from **implemented work**, **Laravel scheduler definitions**, and **observed execution**. An enabled flag does not automatically create a job, run a worker, or prove that the host cron service is working.

## Runtime classifications

| Registry key | Actual implementation | Execution evidence |
| --- | --- | --- |
| `metadata.refresh` | Reserved placeholder; the CLI command does not refresh metadata | No effective maintenance operation. Toggle unavailable until a real implementation ships |
| `cache.warm` | Reserved placeholder; the CLI command does not warm caches | Toggle unavailable until a real implementation ships |
| `health.snapshot` | Reserved placeholder; not the same as `ops:monitor` | Toggle unavailable; real snapshots run through the separate, scheduled `ops:monitor` command |
| `notification.digest` | Implemented private-message email digest (`pm:digest daily`) | Manually invokable only. No recurring schedule is currently configured; enabling the toggle does **not** start email delivery |
| `tracker.peer.cleanup` | Critical ghost-peer cleanup | Laravel scheduler definition (every ten minutes) and real `ops_job_status` completion timestamps |
| `tracker.announce.integrity` | Critical tracker announce validation | Runs in the tracker request path; not a scheduled command |
| `tracker.ratio.accounting` | Critical tracker accounting | Runs in the tracker request path; not a scheduled command |

The Sysop dashboard reads Laravel's `schedule:list` output, which initializes the registered scheduler definitions to check whether a configured **scheduled** command is declared, and reads recorded `ops_job_status` success/failure timestamps. It never labels a declared command as executed if no successful run is recorded. The scheduler heartbeat in the live operations panel is the separate evidence for `php artisan schedule:run` execution.

Placeholders now return a nonzero CLI exit code and an explicit 'not implemented' error when invoked while enabled, instead of an unearned success message. A disabled placeholder still follows the existing safe skip behavior. Placeholder and critical job states cannot be edited through the Sysop runtime toggle endpoint. Saved historical values are kept for compatibility, but are not effective operations. Critical tracker tasks ignore any persisted disabled flag.

**No web action creates a cron entry or runs shell commands.** Enabling the supported manual digest makes the command available to operators; a real recurring schedule requires a separately designed, reviewed deployment and email delivery policy.

## Verification

- Check `php artisan schedule:list` to see **configured** tasks; this does not confirm execution.
- Use **Sysop → Operations** to review the scheduler heartbeat, last completed runs and whether each configured job exists.
- Run `php artisan ops:monitor` from an authorized server shell only if explicit diagnostics are desired; this command captures a real health snapshot and may perform write checks.
- Do not interpret `runtime:health-snapshot`, `runtime:cache-warm`, or `runtime:metadata-refresh` as operational until implemented.

No migrations or frontend asset build are needed for this change. PHP source/Blade changes require a normal application update and `php artisan optimize:clear`.
