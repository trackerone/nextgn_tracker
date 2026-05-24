# Production Operations

Laravel-native runbook for deployment, updates, and recovery.

## Recommended production platform

For detailed server/runtime requirements, use [STACK-BASELINE.md](STACK-BASELINE.md) as the canonical baseline.


- Ubuntu **24.04 LTS** host baseline.
- PHP **8.4+** runtime (CLI/FPM).
- Node **20.x–25.x** for asset builds.
- Redis **7+** for production cache/queue.
- MySQL/MariaDB/PostgreSQL recommended for production DB.

## Required production environment

For hardening rationale and full controls, see [security/production-hardening.md](security/production-hardening.md).


```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://your-domain.example
NEXTGN_PRODUCTION_HARDENING=true
```

Set database/cache/queue/session values for your environment before boot.

## First production deployment (copy/paste order)

```bash
git clone https://github.com/trackerone/nextgn_tracker.git
cd nextgn_tracker
cp .env.example .env
```

Set production env values in `.env` (or platform secret manager), then run:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan nextgn:production-check
```

Common failure points and fixes:
- `APP_KEY` error: rerun `php artisan key:generate --force` and persist value in secrets.
- DB connection failed: verify host/user/password/database/port and network ACLs.
- Production check fails: align settings from `docs/security/production-hardening.md` before go-live.

## Runtime processes

Run as separate processes/services:

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --tries=3 --timeout=90
php artisan schedule:run
```

- Queue workers: restart after each deploy with `php artisan queue:restart`.
- Scheduler: run `schedule:run` every minute via cron/platform scheduler.

## Updating an existing installation

```bash
cd /path/to/nextgn_tracker
git fetch --all
git checkout <release-branch-or-tag>
git pull --ff-only
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan nextgn:production-check
```

Common failure points and fixes:
- Stale config/feature flags: run `php artisan config:clear` then `php artisan config:cache`.
- Jobs not processing after deploy: confirm worker is running and restart queue workers.

## Storage permissions and ownership

If write failures appear (`Permission denied`):

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## Backups and log rotation baseline

- Back up database daily (and before each release).
- Back up `.env`/secret configuration through your platform secret manager policy.
- Rotate and retain application/security logs via platform or OS logrotate policy.

## Troubleshooting quick commands

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
php artisan about
```

Use `GET /health` for basic runtime probe.


## Related runbooks

- [Upgrading safely](installation/upgrading.md)
- [Backups and restore](operations/backups-and-restore.md)
- [Monitoring and alerting](operations/monitoring-and-alerting.md)
- [Process management](operations/process-management.md)

## Operational responsibility model

- **Admin/staff panel**: site/community workflows (moderation, users, invites, content, and metadata controls).
- **Sysop operations dashboard**: read-only runtime visibility and operational warnings for production readiness.
- **Root/server shell access**: deployments, package/composer/npm installs, migrations, backups/restores, OS maintenance, and service administration.

The sysop dashboard is intentionally non-destructive and does not expose secrets, `.env` values, or command execution.
