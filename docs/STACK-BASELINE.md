# NextGN Tracker Stack Baseline

## Core runtime
- **Laravel**: 12.x (composer constraint `^12.0`)
- **PHP**: >= 8.3 (target 8.4 across Docker/CI)
- **Node.js**: >= 20 and < 26 (Node 24 LTS is the primary target)
- **Database**: MySQL/MariaDB (InnoDB) in dev/prod, with SQLite allowed for tests only

## Notes
- Composer constraints allow minor and patch upgrades without code changes; follow the official Laravel upgrade guide for major jumps (e.g. 12 → 13).
- Docker, Render and CI workflows are aligned with PHP 8.4 and Node 24—update the containers together when bumping versions.
- Frontend tooling mirrors the Laravel 12 default stack (Vite 5, Tailwind CSS, shadcn/ui, lucide-react). Blade templates must rely on `@vite()` instead of legacy `mix()` helpers.
- When upgrading further, re-run `composer update`, `npm install`, and regenerate build assets via `npm run build`.
- Tracker services (announce, scrape, invite) require TLS termination, strict rate limiting, and passkey validation; keep those flows in sync with `docs/SECURITY-OVERVIEW.md` when making changes.
