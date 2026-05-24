# NextGN Tracker stack baseline

## Core runtime

- **Laravel**: 13.x (`laravel/framework` constraint `^13.7`).
- **PHP**: 8.4+ (`composer.json` requires `^8.4`; Docker uses `php:8.4-cli`).
- **Node.js**: >=20 and <26 (`package.json` engines).
- **Frontend**: Vite 5, React 18, TypeScript, Tailwind CSS, shadcn/ui, and lucide-react.
- **Database**: Laravel-supported relational databases. The container accepts SQLite, MySQL/MariaDB, PostgreSQL, or SQL Server env wiring; tests commonly use SQLite.

## Operational notes

- Keep Composer, Docker, Render, and CI runtime versions aligned when bumping PHP or Laravel.
- Blade templates should use `@vite()`; do not reintroduce Laravel Mix assets.
- Tracker announce/scrape, uploads, API HMAC, and health checks are documented in the focused docs linked from `README.md`.


## Recommended production baseline (Ubuntu 24.04 LTS)

- **OS**: Ubuntu 24.04 LTS.
- **Web**: Nginx (TLS termination) in front of Laravel runtime.
- **PHP runtime**: PHP 8.4 FPM/CLI aligned with Composer constraints.
- **Database**: MySQL 8.0+/MariaDB 10.11+ or PostgreSQL 15+ (SQLite only for local/test).
- **Cache/queue**: Redis 7+ for production queues/cache/session where needed.
- **Process model**: separate web, queue worker, and scheduler processes.

See `docs/INSTALLATION.md` for local bootstrap and `docs/PRODUCTION-OPERATIONS.md` for deployment/update runbooks.
