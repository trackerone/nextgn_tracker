<h1 align="center">NextGN Tracker</h1>

<p align="center">
  <!-- Stack -->
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13.x" />
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4+" />
  <img src="https://img.shields.io/badge/Node-20--24_LTS-339933?style=flat-square&logo=node.js&logoColor=white" alt="Node 20-24 LTS" />
  <img src="https://img.shields.io/badge/Vite-Assets-646CFF?style=flat-square&logo=vite&logoColor=white" alt="Vite Assets" />
  <img src="https://img.shields.io/badge/License-MIT-3B82F6?style=flat-square" alt="License MIT" />
</p>

<p align="center">
  <!-- CI -->
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg" alt="CI-PHP" />
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml/badge.svg" alt="PHP Lint (Pint)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml/badge.svg" alt="PHP Static Analysis (Larastan)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml/badge.svg" alt="PHPUnit Test (Pest)" />
  </a>

  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml/badge.svg" alt="Compile Assets (Vite)" />
  </a>
</p>


<h2>Overview</h2>

NextGN Tracker is a modern Laravel-based BitTorrent platform under active development.
It is **not** a traditional tracker that stores all release semantics directly on `torrents`.
Instead, it separates operational torrent state from canonical release metadata.

## Core model

- `torrents` stores tracker and lifecycle concerns (visibility, moderation state, swarm-facing fields, uploader relations, etc.).
- `torrent_metadata` stores canonical release metadata.
- Metadata reads are normalized through `TorrentMetadataView`, so API and web surfaces resolve metadata consistently.

## Current capabilities

- Authenticated torrent browse, detail, download, and magnet flows.
- Upload pipeline with metadata extraction and persistence to `torrent_metadata`.
- Staff moderation flows (approve/reject/soft-delete) for pending uploads.
- API endpoints for torrent browse/details/download, uploads, moderation queue/actions, and “my uploads”.
- Forum, private messaging, invite administration, and security/audit log surfaces.

## Tech stack

- PHP 8.3+
- Laravel 12
- MySQL/MariaDB
- Vite + React + TypeScript + Tailwind
- shadcn/ui and lucide-react
- Pest + PHPUnit, PHPStan/Larastan, Rector, Pint

## Local setup

```bash
git clone https://github.com/trackerone/nextgn_tracker.git
cd nextgn_tracker

cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate

php artisan serve --port=8000
npm run dev
```

## Local quality checks

```bash
composer lint
composer analyse
composer rector
composer test
npm run build
```
