# Production operations

Laravel-native runbook for production visibility and recovery.

## Runtime processes

Run web, queue worker, and scheduler separately.

- **Web**: the Docker image runs as the non-root `nextgn` user and starts `tools/entrypoint.sh`, which validates production env, prepares writable directories, runs `storage:link`, warms config/route/view caches, and serves `public/` on `$PORT` (default `10000`).
- **Queue worker**: use `php artisan queue:work --tries=3 --timeout=90` with the same release and environment as web. Run `php artisan queue:restart` after deployments.
- **Scheduler**: run `php artisan schedule:run` once per minute from cron/platform scheduler. Current scheduled commands send private-message digests daily at 07:00 UTC and weekly on Monday at 07:30 UTC.

## Required production environment

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION` and matching database settings for SQLite, MySQL/MariaDB, PostgreSQL, or SQL Server
- `LOG_CHANNEL=stderr` is recommended for containers

## Deployment checklist

1. Build/install dependencies for PHP 8.4 and Node >=20 <26.
2. Provide production secrets through the deployment platform, not the image.
3. Run `php artisan migrate --force` before serving traffic.
4. Start/restart queue workers and ensure scheduler execution if those features are enabled.
5. Verify `GET /health` returns a minimal OK JSON response.

## Writable paths

The runtime user must be able to write:

- `storage/app/public`
- `storage/app/images`
- `storage/app/torrents`
- `storage/app/nfo`
- `storage/framework/cache`, `storage/framework/views`, and `storage/framework/sessions`
- `storage/logs`
- `bootstrap/cache`

## Failed job visibility

The repository includes database queue and failed-job migrations. Common triage commands:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:forget <failed-job-id>
php artisan queue:flush
```

Review failed jobs during incidents and after deploys touching notifications or external metadata enrichment. Retry only after the root cause is fixed.

## Logs and security-sensitive events

Use `LOG_CHANNEL=stderr` on containers. File logs use `storage/logs/laravel.log`; security logs use `storage/logs/security.log`. Do not log passkeys, API keys, HMAC secrets, provider tokens, raw announce IDs, or plaintext passwords.

## Troubleshooting quick reference

| Symptom | Commands/checks |
| --- | --- |
| App does not boot | `php artisan config:clear`; verify `APP_KEY`, production env flags, and database env vars. |
| Stale routes/config/views | `php artisan optimize:clear`; rebuild config, route, and view caches. |
| Queue jobs stuck | `php artisan queue:failed`; `php artisan queue:restart`; inspect worker logs. |
| Scheduled work missing | `php artisan schedule:list`; verify cron runs `php artisan schedule:run` every minute. |
| Public uploads 404 | `php artisan storage:link`; verify storage/public path permissions. |
| Health check fails | Request `GET /health`; inspect web logs. |
| Permission errors | Ensure runtime user can write `storage/` and `bootstrap/cache/`. |
| Security review | Check admin audit/security views and the `security` log separately. |
