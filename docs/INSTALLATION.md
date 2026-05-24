# Installation and First Boot (Ubuntu 24.04 LTS)

Operator-focused setup guide for a fresh local or test environment.

## 1) Prerequisites

Use these minimum versions:

- PHP **8.4+** with extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`.
- Composer **2.7+**.
- Node.js **20.x to 25.x** and npm **10+**.
- A supported SQL database (MySQL/MariaDB, PostgreSQL, SQL Server, or SQLite).
- Redis recommended for production cache/queue.

Baseline platform for this guide: **Ubuntu 24.04 LTS**.

## 2) Clone repository

```bash
git clone https://github.com/trackerone/nextgn_tracker.git
cd nextgn_tracker
```

Common failure:
- `Repository not found`: verify GitHub org/repo name and access rights.

## 3) Create environment file

```bash
cp .env.example .env
```

Then set at least:

```env
APP_NAME="NextGN Tracker"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/nextgn_tracker/database/database.sqlite

CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
```

For SQLite local setup:

```bash
mkdir -p database
touch database/database.sqlite
```

Common failure:
- `could not find driver`: install/enable the missing PDO extension for your selected DB driver.

## 4) Install dependencies

```bash
composer install
npm install
```

Common failures:
- Composer platform errors: run `php -v` and `composer check-platform-reqs` to confirm PHP/extensions.
- npm dependency resolution issues: remove lock/artifacts and reinstall:

```bash
rm -rf node_modules
npm install
```

## 5) Generate key, migrate database, and prepare runtime

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Common failures:
- `No application encryption key has been specified`: rerun `php artisan key:generate`.
- Migration errors: verify DB credentials and DB service availability.

## 6) Start local services

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
npm run dev
```

Terminal 3 (when queue driver is `database`/`redis`):

```bash
php artisan queue:work --tries=3 --timeout=90
```

Common failure:
- Jobs stuck in queue: run `php artisan queue:failed` and inspect worker output.

## 7) Validate local installation

```bash
php artisan about
php artisan test
npm run build
```

If `npm run build` fails, check Node/npm versions first:

```bash
node -v
npm -v
```

## 8) Reset stale caches/config during troubleshooting

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

Use this after `.env` changes, DB driver changes, or route/config edits that do not appear to apply.
