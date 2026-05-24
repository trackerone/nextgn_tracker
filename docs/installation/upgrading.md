# Upgrading NextGN Safely (Ubuntu 24.04 LTS)

Operator runbook for production upgrades and rollback.

## Safe upgrade sequence

Use this sequence for every production release:

1. Validate current system health.
2. Create pre-upgrade backups.
3. Enable maintenance mode.
4. Update code to target tag/branch.
5. Install PHP dependencies.
6. Build frontend assets.
7. Run migrations.
8. Rebuild caches.
9. Restart queue workers.
10. Run production checks and health verification.
11. Disable maintenance mode.

## Pre-upgrade checklist

```bash
cd /path/to/nextgn_tracker
php artisan nextgn:production-check
php artisan queue:failed
php artisan migrate:status
```

Also confirm latest backups exist for DB, storage files, and secrets.

## Step-by-step upgrade commands

## 1) Enter maintenance mode

```bash
cd /path/to/nextgn_tracker
php artisan down --retry=60
```

## 2) Update code (git pull workflow)

Prefer tags/releases in production:

```bash
git fetch --all --tags
git checkout <release-tag>
git pull --ff-only
```

If your process uses a release branch:

```bash
git fetch --all
git checkout <release-branch>
git pull --ff-only
```

## 3) Install backend dependencies

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Do not run `composer update` directly in production unless this is explicitly part of your release process.

## 4) Build frontend assets

```bash
npm ci
npm run build
```

## 5) Run migrations

```bash
php artisan migrate --force
```

## 6) Clear and rebuild caches

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7) Restart workers

```bash
php artisan queue:restart
```

## 8) Verify deployment health

```bash
php artisan nextgn:production-check
curl -fsS https://your-domain.example/health
php artisan about
```

## 9) Exit maintenance mode

```bash
php artisan up
```

## Handling failed migrations

If `php artisan migrate --force` fails:

1. Keep maintenance mode enabled.
2. Capture exact migration error output.
3. Check migration state:

```bash
php artisan migrate:status
```

4. If failure is safe to fix forward, apply corrected release and rerun migration.
5. If failure is destructive/inconsistent, restore DB/files from pre-upgrade backup and roll back code to prior release.

Do not reopen traffic until schema/application versions are compatible.

## Rollback approach

Rollback should match failure type:

- **Code-only rollback:** use when DB schema is backward-compatible with previous release.
- **Full rollback:** restore DB/files + previous release tag when migration/data changes are not backward-compatible.

Example code rollback:

```bash
php artisan down --retry=60
git checkout <previous-release-tag>
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan nextgn:production-check
php artisan up
```

## Post-upgrade validation checklist

Validate at minimum:

- `GET /health` success.
- No unexpected increase in 5xx errors.
- Queue workers process jobs.
- Key operator flows (login, torrent browse, torrent detail, download) work.
- `php artisan nextgn:production-check` returns success.

## Automation notes

For CI/CD pipelines, preserve this order:

1. Backup step.
2. Maintenance mode step.
3. Deploy/build/migrate/cache/queue restart steps.
4. `nextgn:production-check` step.
5. Health probe step.
6. Maintenance mode off step.

If any verification fails, keep maintenance mode on and execute rollback playbook.
