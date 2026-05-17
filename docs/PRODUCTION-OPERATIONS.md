# Production operations

Laravel-native runbook for production visibility and recovery.

## Runtime processes

Run web, queue worker, and scheduler separately. Web uses `tools/entrypoint.sh`.
Queue workers use `php artisan queue:work --tries=3 --timeout=90` with
`QUEUE_CONNECTION=database` or another durable driver and the same release/env as
web. Scheduler cron runs `php artisan schedule:run` once per minute. After every
deployment, run `php artisan queue:restart` so workers reload code/configuration.

## Deployment checklist

1. Publish with production env vars: `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_KEY`, `APP_URL`, and database settings.
2. Run `php artisan migrate --force` before serving traffic.
3. Rebuild caches: `config:clear && config:cache`, `route:clear && route:cache`,
   `view:clear && view:cache`, and `event:clear && event:cache`.
4. Run `php artisan storage:link`, `php artisan queue:restart`, confirm scheduler
   cron executes `php artisan schedule:run`, and verify `GET /health`.

## Failed job visibility

The repository includes `jobs` and `failed_jobs` migrations for database-backed
queues after `php artisan migrate --force` has run. Common triage commands:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:forget <failed-job-id>
php artisan queue:flush
```

Review `queue:failed` during incidents and after deploys touching notifications
or external metadata enrichment. Retry only after the root cause is fixed. Failed
external metadata enrichment logs include torrent id, queue attempt, and
exception class, but never provider credentials or user secrets.

## Scheduler expectations

`app/Console/Kernel.php` schedules `pm:digest daily` every day at 07:00 UTC and
`pm:digest weekly` every Monday at 07:30 UTC. Use `php artisan schedule:list`
during deploy verification when available, and inspect scheduler/platform logs if
expected digest notifications are missing.

## Logs and security-sensitive events

Use `LOG_CHANNEL=stderr` on containers. File logs use `storage/logs/laravel.log`.
Security-sensitive events are separated through the `security` channel, admin
audit/security surfaces, and `storage/logs/security.log`. Do not log passkeys, API
keys, HMAC secrets, provider tokens, raw announce IDs, or plaintext passwords.

## Troubleshooting quick reference

| Symptom | Commands/checks |
| --- | --- |
| App does not boot | `php artisan config:clear`; verify `APP_KEY`, production env flags, and database env vars. |
| Stale routes/config/views | `php artisan optimize:clear`; rebuild config, route, and view caches. |
| Queue jobs stuck | `php artisan queue:failed`; `php artisan queue:restart`; inspect worker logs. |
| Scheduled work missing | `php artisan schedule:list`; verify cron runs `php artisan schedule:run` every minute. |
| Public uploads 404 | `php artisan storage:link`; verify storage/public path permissions. |
| Health check fails | Request `GET /health`; inspect web logs and database connectivity. |
| Permission errors | Ensure runtime user can write `storage/` and `bootstrap/cache/`. |
| Security review | Check admin audit/security views and the `security` log separately. |
