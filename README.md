<h1 align="center">NextGN Tracker</h1>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13.x" />
  <img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4+" />
  <img src="https://img.shields.io/badge/Node-20--25-339933?style=flat-square&logo=node.js&logoColor=white" alt="Node 20-25" />
  <img src="https://img.shields.io/badge/Vite-Assets-646CFF?style=flat-square&logo=vite&logoColor=white" alt="Vite Assets" />
  <img src="https://img.shields.io/badge/License-MIT-3B82F6?style=flat-square" alt="License MIT" />
</p>
<p align="center">
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-backend.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-backend.yml/badge.svg" alt="CI Backend" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg" alt="CI-PHP" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml/badge.svg" alt="PHP Lint (Pint)" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml/badge.svg" alt="PHP Static Analysis (Larastan)" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml/badge.svg" alt="PHPUnit Test (Pest)" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml/badge.svg" alt="Compile Assets (Vite)" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-docker.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-docker.yml/badge.svg" alt="Docker Build Smoke" /></a>
</p>

<p align="center">
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-pr-review.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-pr-review.yml/badge.svg" alt="Codex PR Review" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-issue-implementation.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-issue-implementation.yml/badge.svg" alt="Codex Issue Implementation Intake" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-nightly-maintenance.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/codex-nightly-maintenance.yml/badge.svg" alt="Codex Nightly Maintenance" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/pint-fix.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/pint-fix.yml/badge.svg" alt="Pint Fix" /></a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/lockfile-update.yml"><img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/lockfile-update.yml/badge.svg" alt="Repair composer.lock (Laravel 12 baseline)" /></a>
</p>

## Overview

NextGN Tracker is a modern Laravel-based BitTorrent platform under active development.

## CI status

The badges above mirror the active GitHub Actions workflows for PHP linting, static analysis, tests, backend checks, frontend asset compilation, Docker/runtime validation, and repository automation.

## Core model

- `torrents` stores tracker and lifecycle concerns.
- `torrent_metadata` stores canonical release metadata.
- All metadata reads are normalized through `TorrentMetadataView`.

## Quick start (local)

Use the full installation guide for complete setup, troubleshooting, and environment details:

- [docs/INSTALLATION.md](docs/INSTALLATION.md)

Minimal bootstrap:

```bash
git clone https://github.com/trackerone/nextgn_tracker.git
cd nextgn_tracker
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

## Production runtime

The production Docker image uses FrankenPHP/Caddy to serve the Laravel application from `public/`. Laravel worker mode is intentionally not enabled in this runtime slice; it can be evaluated later after compatibility and load testing. Local development can continue to use `php artisan serve` with Vite as shown above.

## Documentation index

Use [docs/README.md](docs/README.md) for the full documentation map.

Key runbooks:

- [Stack baseline](docs/STACK-BASELINE.md)
- [Frontend setup](docs/FRONTEND-SETUP.md)
- [Production operations](docs/PRODUCTION-OPERATIONS.md)
- [Production hardening](docs/security/production-hardening.md)
- [Security checklist](docs/SECURITY-CHECKLIST.md)
