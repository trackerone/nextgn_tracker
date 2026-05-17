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
