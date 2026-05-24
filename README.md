<h1 align="center">NextGN Tracker</h1>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13.x" />
  <img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4+" />
  <img src="https://img.shields.io/badge/Node-20--25-339933?style=flat-square&logo=node.js&logoColor=white" alt="Node 20-25" />
  <img src="https://img.shields.io/badge/Vite-Assets-646CFF?style=flat-square&logo=vite&logoColor=white" alt="Vite Assets" />
  <img src="https://img.shields.io/badge/License-MIT-3B82F6?style=flat-square" alt="License MIT" />
</p>

## Overview

NextGN Tracker is a modern Laravel-based BitTorrent platform under active development.

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

## Documentation index

Use [docs/README.md](docs/README.md) for the full documentation map.

Key runbooks:

- [Stack baseline](docs/STACK-BASELINE.md)
- [Frontend setup](docs/FRONTEND-SETUP.md)
- [Production operations](docs/PRODUCTION-OPERATIONS.md)
- [Production hardening](docs/security/production-hardening.md)
- [Security checklist](docs/SECURITY-CHECKLIST.md)
