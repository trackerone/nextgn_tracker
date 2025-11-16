# NextGN Tracker

NextGN Tracker is the next-generation tracker-style web application rebuilt on Laravel 12 with hardened security defaults and a Vite-driven frontend. It replaces the legacy stack entirely and follows modern Laravel conventions.

## Stack
- **Laravel**: 12.x
- **PHP**: >= 8.3 (targeting 8.4 in containers/CI)
- **Node.js**: 20–24 LTS with Vite 5
- **Database**: MySQL/MariaDB by default (update `.env` to match your engine)

## Installation
1. Clone the repository and enter the directory.
2. Copy the environment template and adjust secrets:
   ```bash
   cp .env.example .env
   ```
3. Configure `APP_URL`, database credentials, queues/mail, and any tracker-specific values.
4. Install backend and frontend dependencies:
   ```bash
   composer install
   npm install
   ```
5. Generate the application key and run migrations:
   ```bash
   php artisan key:generate
   php artisan migrate
   ```
6. Build assets for production (`npm run build`) or start Vite in dev mode (`npm run dev`).

## Local development
- Serve the backend via `php artisan serve` (or Sail/Herd). The dev server listens on http://localhost:8000 by default.
- Run `npm run dev` in another terminal to launch Vite with hot reloading. Blade templates should include `@vite(['resources/css/app.css','resources/js/app.tsx'])`.

## Security & reference docs
- `docs/SECURITY-OVERVIEW.md` – describes auth hardening, tracker requirements, rate limiting, and API passkey/HMAC expectations.
- `docs/SECURITY-CHECKLIST.md` – deployment checklist that covers headers, TLS, env handling, and log redaction.
- `docs/STACK-BASELINE.md` – outlines the supported runtime versions (PHP, Node, Laravel) and upgrade guidance.
- `docs/FRONTEND-SETUP.md` – explains how the Vite/Tailwind/shadcn UI is wired and how to extend entrypoints.

## Deployment
- Ensure environment variables, queues, and cache stores are configured for production (Redis/MySQL, S3, mail, etc.).
- Run `php artisan migrate --force` and `npm run build` (or `npm run build && npm run preview` for smoke tests) as part of the release pipeline.
- Serve the built app behind nginx/Apache or your platform (Render, Docker, Fly). Point the web server to `/public`, ensure PHP-FPM has write access to `storage/` and `bootstrap/cache`, and reload caches with `php artisan config:cache route:cache view:cache` after deployment.
