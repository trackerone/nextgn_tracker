# NextGN Tracker

Baseline Laravel 11 application with modern tooling and security defaults.

## Prerequisites

- PHP 8.3+
- Composer 2
- Node.js 20+
- npm 10+

## Getting started

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan serve
```

The application boots on http://localhost:8000 with frontend assets built by Vite.

## Testing

```bash
composer test
npm run lint
```

> TODO: configure ESLint and integrate ExtendedPDO/FluentPDO when persistence is introduced.

## Roles schema & seed

User permissions are organised in the `roles` table, ranging from `sysop` (level 12) down to `newbie` (level 0). Seed or refresh the hierarchy with:

```bash
php artisan db:seed --class=RoleSeeder
```

Running the database seeder ensures the role ladder exists and backfills any existing users without a role to the default `newbie` record.
