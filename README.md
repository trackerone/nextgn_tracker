# NextGN Tracker

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
