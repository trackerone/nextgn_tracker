# NextGN Tracker

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php)
![Node](https://img.shields.io/badge/Node-20--24_LTS-339933?style=for-the-badge&logo=node.js)
![Vite](https://img.shields.io/badge/Frontend-Vite_Assets-646CFF?style=for-the-badge&logo=vite)
![License](https://img.shields.io/badge/License-MIT-0EA5E9?style=for-the-badge)

[![CI - PHP](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg)](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-php.yml)
[![CI - Frontend](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml/badge.svg)](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml)
![Lint](https://img.shields.io/badge/PHP_Lint-Pint_Passing-0EA5E9?style=flat-square)
![Static Analysis](https://img.shields.io/badge/Static_Analysis-Larastan-22C55E?style=flat-square)
![Tests](https://img.shields.io/badge/Tests-Pest_Passing-16A34A?style=flat-square)

> Replace `YOUR_GITHUB_ORG` with your organization/user slug so the workflow badges track your repository automatically. Static "Passing" text on the Shields.io badges is informational only; the GitHub Actions badges above reflect the real status.

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

## CI / Quality
- **CI - PHP** installs Composer dependencies on PHP 8.3, runs Laravel Pint via `composer lint`, executes Pest tests via `composer test`, and performs Larastan static analysis via `composer analyse`.
- **CI - Frontend** provisions Node.js 22 LTS, installs npm dependencies, and validates the production asset build with `npm run build`.
- These workflows trigger on pushes and pull requests targeting main/master as well as feature branches to guard regressions before merge.
- Reproduce the same checks locally with `composer lint`, `composer analyse`, `composer test`, and `npm run build`.

## Contributing
Thanks for helping shape NextGN Tracker. Review the contribution workflow, coding standards, and security rules before opening a pull request.

[See CONTRIBUTING.md for details](./CONTRIBUTING.md)

## Security & reference docs
- `docs/SECURITY-OVERVIEW.md` – describes auth hardening, tracker requirements, rate limiting, and API passkey/HMAC expectations.
- `docs/SECURITY-CHECKLIST.md` – deployment checklist that covers headers, TLS, env handling, and log redaction.
- `docs/STACK-BASELINE.md` – outlines the supported runtime versions (PHP, Node, Laravel) and upgrade guidance.
- `docs/FRONTEND-SETUP.md` – explains how the Vite/Tailwind/shadcn UI is wired and how to extend entrypoints.

## Deployment
- Ensure environment variables, queues, and cache stores are configured for production (Redis/MySQL, S3, mail, etc.).
- Run `php artisan migrate --force` and `npm run build` (or `npm run build && npm run preview` for smoke tests) as part of the release pipeline.
- Serve the built app behind nginx/Apache or your platform (Render, Docker, Fly). Point the web server to `/public`, ensure PHP-FPM has write access to `storage/` and `bootstrap/cache`, and reload caches with `php artisan config:cache route:cache view:cache` after deployment.
