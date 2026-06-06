# Container Hardening

NextGN's production container runs Laravel on FrankenPHP/Caddy with PHP 8.4. This document records the current controls that are implemented in the Docker runtime and the remaining hardening work that should be handled in later, focused changes.

## Current runtime

- Base image: `dunglas/frankenphp:1-php8.4-bookworm`.
- Web runtime: FrankenPHP/Caddy via `frankenphp run --config /app/deploy/frankenphp/Caddyfile`.
- The legacy `php -S` development server is not used by the production image.
- FrankenPHP worker mode is not enabled. The current container keeps the simpler request lifecycle until worker-mode behavior can be load-tested against Laravel boot, caches, database access, and long-lived process state.

## Runtime user

The Dockerfile creates the `nextgn` group and user with build-time configurable `APP_GID` and `APP_UID` values that default to `1000`. The final image switches to `USER nextgn`, so the default container process should not start as root.

CI verifies this by running `docker run --rm --entrypoint id nextgn-tracker:test` and failing if the output contains `uid=0`.

## Writable paths

The production image prepares only the paths that Laravel, Caddy, and temporary runtime work need to write:

- `storage/app/public`
- `storage/app/images`
- `storage/app/torrents`
- `storage/app/nfo`
- `storage/framework/cache`
- `storage/framework/views`
- `storage/framework/sessions`
- `storage/logs`
- `bootstrap/cache`
- `/config/caddy`
- `/data/caddy`
- `/tmp`

The Dockerfile assigns application and Caddy runtime ownership to `nextgn:nextgn` and grants owner/group read-write-execute-on-directories permissions for the Laravel and Caddy writable paths. It does not use `chmod 777`.

`/tmp` is expected to remain mode `1777`, matching standard Unix temporary-directory behavior: any runtime user can create temporary files, and the sticky bit prevents users from deleting files they do not own. The entrypoint keeps `TMPDIR=/tmp` for PHP and cache/temp writes.

## Production environment validation

The entrypoint defaults to `APP_ENV=production` and `APP_DEBUG=false`. In production it refuses to start if `APP_DEBUG` is anything other than `false`.

The same startup validation requires:

- `APP_KEY`
- `DB_CONNECTION`
- `DB_DATABASE` when `DB_CONNECTION=sqlite`
- `DB_HOST`, `DB_DATABASE`, and `DB_USERNAME` when `DB_CONNECTION` is `mysql`, `mariadb`, `pgsql`, or `sqlsrv`

This keeps the existing APP_KEY and database environment checks intact before Laravel cache warmup and FrankenPHP startup.

## Composer dependencies

The Docker image runs `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, so Composer development dependencies are not installed during the production image build. The `.dockerignore` excludes local `vendor/` and `node_modules/` directories from the build context so host development dependencies are not copied into the image before the production install.

## Docker CI coverage

`.github/workflows/ci-docker.yml` runs on pushes and pull requests targeting `main` or `develop`. It is a container/runtime smoke test, not a full application acceptance test. It verifies:

- The Docker image builds with `docker build -t nextgn-tracker:test .`.
- PHP is available with `docker run --rm nextgn-tracker:test php -v`.
- FrankenPHP is available with `frankenphp version` or `frankenphp --version`.
- The default runtime user is not root.
- The FrankenPHP/Caddy configuration validates inside the built image without starting the Laravel entrypoint.

The workflow intentionally does not prove full Laravel application readiness. A full production startup smoke would currently require the entrypoint to run Laravel cache warmup (`config:cache`, `route:cache`, and `view:cache`) against dummy SQLite state before FrankenPHP starts, which is not deterministic enough for a reliable container smoke test yet.

Full Laravel production startup smoke testing is deferred until the app has a deterministic test environment with migrations and a seeded database suitable for production boot checks. Until then, full app readiness remains covered by the separate PHP test suite and should be expanded with future deployment smoke tests that run against real deployment configuration.

## Healthcheck status

No Docker `HEALTHCHECK` instruction is added in this change. Runtime healthcheck behavior should be decided with deployment-specific timeout, interval, and failure-threshold expectations rather than introduced implicitly.

## Known future improvements

- Convert to a multi-stage Docker build when it can be done safely and reviewed separately.
- Reduce image size by pruning build-only packages or splitting build/runtime layers.
- Add vulnerability scanning for the built image in CI.
- Add a runtime Docker healthcheck once deployment expectations are agreed.
- Load-test FrankenPHP worker mode before enabling it in production.
