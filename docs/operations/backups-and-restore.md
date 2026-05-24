# Backups and Restore (Ubuntu 24.04 LTS)

Operator runbook for backup policy, restore procedure, and rollback readiness.

## What must never be lost

At minimum, protect these assets in every backup plan:

- Production database (all application state).
- `storage/app/torrents` (uploaded `.torrent` files).
- `storage/app/nfo` (uploaded NFO files).
- `.env` or your secret-manager equivalent (DB credentials, `APP_KEY`, queue/cache config, API settings).

If any of these are missing, full service recovery is incomplete.

## Backup strategy by component

## 1) Database backup strategy

- Run full database backups at least daily.
- Run an additional pre-release backup before each production upgrade.
- Use consistent snapshots (single transaction for InnoDB/PostgreSQL).
- Encrypt backup artifacts at rest and in transit.
- Store off-host copies (different disk/volume or object storage target).

### MySQL / MariaDB backup example

```bash
export BACKUP_DIR=/var/backups/nextgn
export TS="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --default-character-set=utf8mb4 \
  -h "$DB_HOST" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" \
  | gzip > "$BACKUP_DIR/db-${TS}.sql.gz"
```

### PostgreSQL backup example

```bash
export BACKUP_DIR=/var/backups/nextgn
export TS="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

PGPASSWORD="$DB_PASSWORD" pg_dump \
  --format=plain \
  --no-owner \
  --no-privileges \
  --host="$DB_HOST" \
  --username="$DB_USERNAME" \
  "$DB_DATABASE" \
  | gzip > "$BACKUP_DIR/db-${TS}.sql.gz"
```

## 2) Storage backup strategy (`torrent`/`nfo` files)

Back up storage paths together with DB snapshots so metadata and files stay aligned:

- `storage/app/torrents`
- `storage/app/nfo`

Example archive:

```bash
export BACKUP_DIR=/var/backups/nextgn
export TS="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

sudo tar -C /path/to/nextgn_tracker -czf "$BACKUP_DIR/storage-${TS}.tar.gz" \
  storage/app/torrents \
  storage/app/nfo
```

For larger volumes, prefer incremental filesystem snapshots or object storage sync with versioning.

## 3) `.env` backup handling

- Prefer a dedicated secret manager as source of truth.
- If `.env` is file-managed, back it up encrypted only.
- Restrict restore access to operators with production secret privileges.
- Never store unencrypted `.env` in Git repositories.

Example encrypted archive (age):

```bash
age -r "<your-public-key>" -o "/var/backups/nextgn/env-$(date -u +%Y%m%dT%H%M%SZ).age" /path/to/nextgn_tracker/.env
```

## 4) Redis considerations

Redis typically stores cache and queue state:

- Cache entries are rebuildable and usually not required for disaster restore.
- Queue in-flight jobs may be lost if Redis persistence is disabled.

Recommendations:

- Enable Redis persistence (`AOF` and/or `RDB`) if queued job durability matters.
- Plan restart behavior: after restore, run `php artisan queue:restart` to ensure workers reload state.
- Clear stale cache/session data after major recovery if corruption is suspected.

## Retention recommendations

Use at least:

- Daily backups: retain 14–30 days.
- Weekly backups: retain 8–12 weeks.
- Monthly backups: retain 6–12 months.
- Pre-release backups: retain until release is proven stable.

Adjust retention to legal/compliance policy and storage budget.

## Cron examples

Example daily backup job at 02:30 UTC:

```cron
30 2 * * * /usr/local/bin/nextgn-backup.sh >> /var/log/nextgn-backup.log 2>&1
```

Example weekly prune job Sundays at 03:15 UTC (keep 30 days):

```cron
15 3 * * 0 find /var/backups/nextgn -type f -mtime +30 -delete
```

## Restore procedure examples

Always restore into an isolated staging/verification environment first.

## Restore MySQL / MariaDB

```bash
gunzip -c /var/backups/nextgn/db-<timestamp>.sql.gz \
  | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

## Restore PostgreSQL

```bash
gunzip -c /var/backups/nextgn/db-<timestamp>.sql.gz \
  | PGPASSWORD="$DB_PASSWORD" psql \
      --host="$DB_HOST" \
      --username="$DB_USERNAME" \
      --dbname="$DB_DATABASE"
```

## Restore storage files

```bash
sudo tar -C /path/to/nextgn_tracker -xzf /var/backups/nextgn/storage-<timestamp>.tar.gz
sudo chown -R www-data:www-data /path/to/nextgn_tracker/storage
```

## Post-restore validation checklist

Run after DB/files restore and before opening traffic:

```bash
cd /path/to/nextgn_tracker
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status
php artisan queue:restart
php artisan nextgn:production-check
```

Then verify:

- `GET /health` returns success.
- Admin can browse torrents and details.
- Download flow can read personalized `.torrent` files.

## Restore testing and disaster rehearsal

Backups are only valid when restore is tested.

Recommended cadence:

- Monthly restore drill into staging.
- Quarterly full recovery timing test (RTO/RPO validation).
- Restore test after major schema or storage-layout changes.

Track test date, backup artifact used, duration, and issues found.

## Production rollback considerations

When a deployment fails after migrations or config changes:

1. Put app in maintenance mode.
2. Decide rollback type:
   - Code-only rollback (when schema is backward compatible).
   - Full rollback from backup (when destructive migration or data corruption occurred).
3. Restore DB/files from last known good backup when required.
4. Re-deploy matching application commit/tag for that backup.
5. Rebuild caches and restart workers.
6. Run `php artisan nextgn:production-check` before exit from maintenance mode.

Example maintenance mode controls:

```bash
php artisan down --retry=60
# rollback/recovery steps
php artisan up
```
