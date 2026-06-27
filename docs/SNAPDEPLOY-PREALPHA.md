# SnapDeploy Pre-Alpha Demo Bootstrap

## Purpose

This SnapDeploy bootstrap path exists for **pre-alpha UI navigation testing only**. It is intended to make a temporary NextGN environment easy to deploy so reviewers can click through the interface with deterministic demo users and believable non-production tracker data.

This is **not production deployment guidance**. It is also **not** the Ubuntu VPS autoinstaller path.

Do **not** use this bootstrap against real production tracker data, production user accounts, production torrents, or production databases.

## Recommended SnapDeploy environment

Configure real secrets in the SnapDeploy UI. Use the example file as a non-secret starting point only.

```env
APP_ENV=staging
APP_DEBUG=false
NEXTGN_PREALPHA_DEMO=true
```

A full non-secret example is available in `.env.snapdeploy.example`.

## SnapDeploy start command

Keep the repository default Docker runtime unchanged. In SnapDeploy, override the service start command to run the pre-alpha bootstrap script:

```sh
sh /app/scripts/snapdeploy-start.sh
```

The default Dockerfile continues to start through `/app/tools/entrypoint.sh`; the SnapDeploy override is what enables migrations plus optional demo seeding for this pre-alpha path.

## Demo users

`StagingDemoSeeder` creates deterministic users for common role-based navigation checks. Every seeded demo user uses the password `password`.

| Role to test | Email | Password |
| --- | --- | --- |
| Sysop / admin | `mira.sysop@example.test` | `password` |
| Moderator | `noah.mod@example.test` | `password` |
| Uploader | `iris.uploads@example.test` | `password` |
| Power user / archive-oriented member | `theo.archive@example.test` | `password` |
| Normal member | `sam.member@example.test` | `password` |
| Newbie | `jules.newbie@example.test` | `password` |

## How demo seeding works

The SnapDeploy startup script runs Laravel migrations with `--force` on container startup.

Demo data is seeded only when this explicit safety flag is present:

```env
NEXTGN_PREALPHA_DEMO=true
```

When enabled, startup runs:

```sh
php artisan db:seed --class=StagingDemoSeeder --force
```

The seeder creates believable non-production data for UI navigation, including role-specific users and tracker/discovery surfaces suitable for pre-alpha review. The existing production guard inside `StagingDemoSeeder` remains in place; this bootstrap does not make demo data automatic for production.

For local or staging-style manual setup, the underlying commands remain:

```sh
php artisan migrate
php artisan db:seed --class=StagingDemoSeeder
```

## Manual UI testing checklist

Use the demo users above to verify role-based navigation and access boundaries:

- Login with each demo user.
- Browse torrent listings.
- Open torrent details pages.
- Exercise the upload flow as roles that should be able to upload.
- Review moderation and admin areas with staff/sysop accounts.
- Check discovery and recommendation surfaces where available.
- Confirm role-based navigation changes between sysop/admin, moderator, uploader, normal member, and newbie accounts.

## Safety warning

This path is for disposable pre-alpha review environments only. Never point it at real production tracker data, and never enable `NEXTGN_PREALPHA_DEMO=true` for a production tracker deployment.
