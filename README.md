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

## Role middleware examples

Two demo routes illustrate the `role.min:{level}` middleware:

- `GET /admin` &rarr; requires an authenticated, verified user with role level ≥ 10 (admin).
- `GET /mod` &rarr; requires an authenticated, verified user with role level ≥ 8 (moderator).

Behind the scenes a sysop (level 12) bypasses all checks via a `Gate::before` hook, while named gates like `isAdmin` and `isUploader` are available for policy checks.

## User promotion command

Promote an existing account to a higher role directly from the CLI:

```bash
php artisan user:promote user@example.com admin1
```

The command validates the email and role slug before updating the user's `role_id`, returning a non-zero exit code when either lookup fails.

## Forum core

The forum feature introduces the following pieces:

- **Models**: `Topic`, `Post`, and `PostRevision` capture discussions, replies, and edit history (including soft deletes for posts).
- **Services**: `TopicSlugService` ensures unique slugs, while `MarkdownService` renders CommonMark-style input and sanitises the resulting HTML with a strict whitelist.
- **Routes & policies**:
  - Browse topics (`GET /topics`) and inspect a thread (`GET /topics/{slug}`) are public.
  - Authenticated, verified users with role level ≥ `user1` may create topics (`POST /topics`) and replies (`POST /topics/{id}/posts`), both throttled at 60 writes/minute.
  - Moderators (`role.min:8`) can lock/unlock and pin/unpin (`POST /topics/{id}/lock`, `/pin`), and edit/delete posts.
  - Admins (`role.min:10`) may delete topics once the discussion is cleared.
- **Frontend**: React components under `resources/js/components/forum` provide topic lists, thread views, and forms for creating topics or replies with inline moderation controls.

All markdown input is rendered through the `MarkdownService`, which strips scripts/unsafe attributes and preserves formatting such as headings, emphasis, and links.
