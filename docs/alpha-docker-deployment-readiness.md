# Alpha Docker Deployment Readiness

## 1. Purpose

This document prepares the first controlled NextGN alpha server install. It defines the minimum Docker Compose deployment expectations needed to install, configure, smoke-test, back up, and roll back an invite-only alpha without guessing.

This is deployment readiness for controlled alpha. It is not a public launch plan, Kubernetes design, production scaling plan, or full DevOps platform.

## 2. Target server profile

| Item | Alpha recommendation |
| --- | --- |
| Provider | Hetzner Cloud or similar EU VPS |
| OS | Ubuntu 24.04 LTS |
| Size | 2 GB RAM minimum, 1-2 vCPU, 20-40 GB disk minimum |
| Access | SSH key only |
| Firewall | 22, 80, 443 only |
| Deployment | Docker Compose |
| TLS | Let's Encrypt via Caddy, Traefik, Nginx proxy, or host reverse proxy |
| Database | MariaDB/MySQL or PostgreSQL container for alpha |
| Cache/queue | Redis container |
| Backups | Database + storage volumes + `.env` secret handling |

Keep the first alpha VPS boring and reproducible. Prefer one well-understood host, simple named volumes, explicit secrets, and a written rollback point over any cluster or autoscaling design.

## 3. Required services

The current repository includes a Dockerfile based on FrankenPHP/Caddy. An alpha Compose deployment may use that image as the web runtime directly, or place it behind a separate Caddy, Traefik, Nginx, or host reverse proxy for TLS. If a separate proxy is used, it owns HTTPS and forwards traffic to the app/web service.

| Service | Purpose | Required persistent data | Restart expectation | Alpha risk if missing |
| --- | --- | --- | --- | --- |
| Reverse proxy / web | Terminates HTTPS or serves the Laravel web runtime and public assets. | TLS state if the proxy manages Let's Encrypt certificates; otherwise none beyond logs. | Restart automatically unless stopped by an operator. | Users cannot reach the site over HTTPS, generated URLs may be wrong, and RSS/download links may be unsafe to test. |
| App / PHP runtime | Runs Laravel web requests through the production Docker image or equivalent PHP runtime. | Laravel `storage` and `bootstrap/cache` write access; uploaded torrent/NFO/image storage if mounted here. | Restart automatically and be rebuilt per release. | Browse, auth, upload, download, RSS, and staff surfaces are unavailable. |
| Queue worker | Runs `php artisan queue:work` for queued jobs. | No unique local state; needs Redis/database connectivity and shared app storage. | Restart automatically; run `php artisan queue:restart` after deploys. | Background jobs stall, mail/notifications/imports may lag, and alpha operations become misleading. |
| Scheduler | Runs Laravel scheduled tasks, normally `php artisan schedule:run` every minute or a long-running scheduler container loop. | No unique local state; needs app config, database, Redis, and shared storage. | Restart automatically; only one scheduler instance should run. | Scheduled maintenance, cleanup, and tracker/runtime tasks do not execute predictably. |
| Database | Stores application state, users, torrents, moderation, RSS/watch state, feedback intake, and operational records. | Database data volume and backups. | Restart automatically after host/container reboot. | Alpha data is lost or corrupted; no trusted uploader alpha is safe without database persistence. |
| Redis | Provides cache, queue, locks, and optionally sessions depending on `.env.alpha`. | Optional Redis persistence if queue/session durability is required; otherwise disposable cache state. | Restart automatically after host/container reboot. | Queues, cache, locks, or sessions fail depending on configuration. |

## 4. Required persistent volumes

These data paths must survive container rebuilds and image replacement:

- Database data.
- Laravel storage, including `storage/app`, `storage/framework`, and `storage/logs` when logs are file-based.
- Torrent files, normally under `storage/app/torrents` unless configured otherwise.
- NFO/upload/image files, normally under `storage/app/nfo` and `storage/app/images` unless configured otherwise.
- Logs if the deployment stores them outside container stdout/stderr.
- Optional backups directory, preferably on a separate host disk, volume, or off-host target.

A Compose update that removes these volumes is a data-loss event. Treat volume names and mount paths as part of the alpha server contract.

## 5. Required environment variables

Use a server-only `.env.alpha` file, Compose `env_file`, or host secret store. Do not commit real secrets. The examples below are placeholders only.

### Application identity and safety

```env
APP_NAME="NextGN Alpha"
APP_ENV=production
APP_KEY=base64:replace-with-generated-key
APP_DEBUG=false
APP_URL=https://alpha.example.invalid
NEXTGN_PRODUCTION_HARDENING=true
```

### Database connection

```env
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=nextgn_alpha
DB_USERNAME=nextgn_alpha
DB_PASSWORD=replace-with-database-password
```

Use matching container environment values for the database root/user/password/database. PostgreSQL may be used if the selected image and Laravel driver settings are configured consistently.

### Redis, cache, session, and queue

```env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

Confirm the selected session driver matches the desired alpha behavior. File sessions require persistent shared storage; Redis sessions require Redis availability.

### Mail configuration

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.invalid
MAIL_PORT=587
MAIL_USERNAME=replace-with-smtp-user
MAIL_PASSWORD=replace-with-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=alpha@example.invalid
MAIL_FROM_NAME="NextGN Alpha"
```

Mail must be real enough for invite, password reset, notification, and staff workflows that are in alpha scope.

### Filesystem and upload storage

```env
FILESYSTEM_DISK=local
UPLOAD_TORRENTS_DISK=local
UPLOAD_TORRENTS_DIRECTORY=torrents
UPLOAD_NFO_DISK=local
UPLOAD_NFO_DIRECTORY=nfo
UPLOAD_IMAGES_DISK=local
UPLOAD_IMAGES_DIRECTORY=images
```

Align these values with mounted volumes so uploaded `.torrent`, NFO, and image files survive container rebuilds.

### Tracker, RSS, token, and passkey configuration

```env
TRACKER_ANNOUNCE_URL=https://alpha.example.invalid/announce/%s
TRACKER_ANNOUNCE_MIN_INTERVAL=1800
API_HMAC_SECRET=replace-with-api-hmac-secret-if-used
API_REQUIRE_NONCE=true
```

RSS tokens and passkeys are security-sensitive alpha secrets. Verify tokenized feeds and downloads only over HTTPS and rotate any test credentials exposed during setup.

### Scheduler and queue settings

```env
QUEUE_CONNECTION=redis
DB_QUEUE_RETRY_AFTER=90
```

Run queue workers and the scheduler as separate Compose services. Do not rely on ad hoc SSH sessions for normal alpha operation.

### Logging

```env
LOG_CHANNEL=stack
LOG_LEVEL=info
```

Prefer container stdout/stderr for runtime logs unless the alpha host has explicit log file rotation. If `storage/logs` is used, mount and rotate it.

### Trusted proxies and HTTPS expectations

Set `APP_URL` to the public HTTPS origin. If TLS terminates outside the app container, configure the reverse proxy and Laravel trusted proxy behavior so generated links, redirects, RSS links, storage URLs, and tracker announce URLs use HTTPS.

### Rate limit and security configuration

```env
SECURITY_LOCKDOWN=false
SECURITY_RATE_LIMIT_API=60
SECURITY_RATE_LIMIT_MODERATION=30
SECURITY_MAX_INPUT_LENGTH=10000
```

Use existing application defaults unless an alpha-specific limit is intentionally selected and documented.

## 6. First install checklist

- [ ] Create VPS.
- [ ] Add SSH key.
- [ ] Update OS packages.
- [ ] Configure firewall for 22, 80, and 443 only.
- [ ] Install Docker and the Docker Compose plugin.
- [ ] Clone repository or deploy release artifact.
- [ ] Create `.env.alpha` on the server only.
- [ ] Generate `APP_KEY` and persist it in `.env.alpha` or the server secret store.
- [ ] Configure `APP_URL` to the public HTTPS alpha origin.
- [ ] Configure database credentials.
- [ ] Configure Redis.
- [ ] Configure mail.
- [ ] Configure storage paths and persistent volumes.
- [ ] Start containers.
- [ ] Run Composer/npm build only according to the chosen Docker strategy.
- [ ] Run migrations with `php artisan migrate --force`.
- [ ] Create the first sysop/admin/staff user using the existing supported project workflow.
- [ ] Start queue worker.
- [ ] Start scheduler.
- [ ] Confirm HTTPS.
- [ ] Run the smoke test harness.

## 7. Deploy/update procedure

For alpha updates, keep the process simple and reversible:

1. Record the current commit/image tag and deployment time.
2. Take a database and storage snapshot before risky deploys.
3. Pull the new code or release artifact.
4. Rebuild the app image if the Docker strategy builds locally on the VPS.
5. Start or update database and Redis containers without deleting volumes.
6. Run migrations with `php artisan migrate --force` after reviewing the release notes.
7. Clear and rebuild Laravel caches as appropriate: `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
8. Restart the app/web container.
9. Restart queue workers and run `php artisan queue:restart`.
10. Restart or confirm the scheduler container.
11. Run smoke checks.
12. Monitor app, queue, scheduler, database, Redis, proxy, and OS logs.

Keep the exact Compose commands in the server runbook once the final Compose file is selected.

## 8. Backup and restore

Minimum alpha backup requirements:

- Database dump before launch, daily during alpha, and before risky deploys.
- Storage backups for torrent files, NFO files, uploads/images, and any other configured persistent upload paths.
- `.env.alpha` or server secret configuration stored securely outside the repository.
- Restore test into a separate verification environment before trusting the backup process.
- VPS snapshot before risky deploys, migrations, or large data changes.

A tracker alpha without storage/database backup is not safe enough for trusted uploaders.

Restore expectations:

1. Stop write traffic or keep the restored environment isolated.
2. Restore database backup.
3. Restore storage volumes from the matching backup window.
4. Restore `.env.alpha` from secure secret storage.
5. Recreate containers without deleting persistent volumes.
6. Run cache rebuild, migration status, queue restart, and production readiness checks.
7. Run the smoke harness before reopening invites or normal alpha use.

## 9. Rollback plan

A simple alpha rollback plan is required before the first invite:

- Keep the previous image tag or commit reference.
- Take a host/database/storage snapshot before each risky deploy.
- Roll back the app image or checkout only when the database state remains compatible.
- Use Laravel migration rollback only when the migration is known safe to reverse and no alpha data would be lost unexpectedly.
- Restore database and storage from backup if a migration or data change breaks alpha state.
- Mark the incident in the alpha feedback intake with environment, timeline, affected roles, and recovery action.

Rollback is not complete until the smoke harness passes again.

## 10. Post-install smoke test

After server install, the smoke harness is the go/no-go artifact. Use [Livable Alpha Smoke Test Harness](livable-alpha-smoke-test-harness.md) after the Docker alpha deployment is running over HTTPS.

Minimum smoke areas:

- Auth/session.
- Browse/detail.
- Download/magnet.
- Upload/My Uploads.
- Staff moderation.
- RSS/watch.
- Alpha feedback intake.
- Mobile.
- Operations/security/logs.

Do not send alpha invites until all blocking smoke rows pass or are explicitly deferred out of alpha scope.

## 11. Explicit non-goals

- No Kubernetes.
- No autoscaling.
- No public launch.
- No multi-region.
- No managed observability stack.
- No production hardening beyond alpha readiness.
- No recommendation/discovery expansion.
