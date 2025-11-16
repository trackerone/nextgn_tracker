![Contributions](https://img.shields.io/badge/Contributions-Welcome-22C55E?style=for-the-badge&logo=github)
![Stack](https://img.shields.io/badge/Stack-Laravel_12,_PHP_8.3%2B,_Vite-2563EB?style=for-the-badge)
[![CI - PHP](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg)](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-php.yml)
[![CI - Frontend](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml/badge.svg)](https://github.com/YOUR_GITHUB_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml)

> Update the `YOUR_GITHUB_ORG` placeholder in the badge URLs above to match your actual GitHub organization or username so the workflow indicators point to the correct repository.

# Contributing to NextGN Tracker

## Introduction
NextGN Tracker is a modern, security-focused tracker engine powered by Laravel 12, PHP 8.3+, and a Vite/Tailwind-driven frontend. Contributions help us keep the platform hardened, fast, and delightful. This guide explains how to participate responsibly and efficiently.

## Code of Conduct
We expect everyone to follow the [Code of Conduct](./CODE_OF_CONDUCT.md). Be respectful, patient, and considerate. Harassment, exclusionary behavior, or dismissive feedback is never acceptable.

## Getting Started
1. Review the [README](./README.md) for stack requirements, install steps, and CI expectations.
2. Search existing issues/PRs before opening a new ticket to avoid duplicates.
3. For substantial work, open an issue or discussion outlining the proposal before writing code.
4. Small documentation or typo fixes can go straight to a PR, but still link to an issue if one exists.

## Development Setup
- Clone the repository and install dependencies: `composer install` and `npm install`.
- Copy `.env.example` to `.env` and configure database, queues, mail, and tracker-specific values.
- Run migrations with `php artisan migrate` and boot the dev servers (`php artisan serve`, `npm run dev`).
- Keep dependencies current with `composer update` (backend) and `npm update` (frontend) when your change requires it. Coordinate breaking updates via issues first.

## Branching Strategy & Workflow
- `main` tracks production-ready releases. Create PRs against `develop` (or the primary integration branch if named differently in your fork).
- Use descriptive feature branches: `git checkout -b feature/improved-search` or `git checkout -b fix/upload-timeout`.
- Rebase frequently on top of `develop` to avoid merge conflicts: `git fetch origin && git rebase origin/develop`.
- Keep commits focused and under ~200 lines; squash locally when necessary to maintain a clean history.

## Coding Standards (PHP / Frontend)
- Follow PSR-12, strict types, and Laravel best practices. Run `composer lint` (Laravel Pint) before committing.
- Tests must pass via `composer test` (Pest or PHPUnit). Favor Pest for new suites unless a PHPUnit extension is required.
- Run `composer analyse` to execute Larastan at max level—fix or justify every finding.
- For frontend work, run `npm run build` to validate the Vite pipeline. Add/adjust tests if you touch shared components.
- Never bypass the centralized upload pipeline, never execute raw SQL with user input, and always sanitize/escape user-generated content via the ContentSafety utilities.

## Security & Responsible Disclosure
- Report vulnerabilities privately via the channels listed in `docs/SECURITY-OVERVIEW.md` before filing a public issue.
- Encrypt sensitive proof-of-concept data and share only with maintainers until a fix ships.
- When contributing security fixes, describe impact, mitigation, and regression tests. Ensure rate limits, CSRF, and authorization checks remain intact.

## Submitting Pull Requests
1. Ensure your branch is up to date with `develop`.
2. Run all required checks locally:
   - `composer lint`
   - `composer analyse`
   - `composer test`
   - `npm run build`
3. Push your branch: `git push origin feature/improved-search`.
4. Open a PR against `develop`, referencing related issues. Fill out the PR template completely and describe testing performed.
5. PRs must stay green on **CI - PHP** and **CI - Frontend** workflows. Maintainers may re-run CI or request adjustments before merging.

## Style Guidelines (PHP, Blade, JS, Markdown)
- **PHP**: Use typed properties, avoid facades in domain logic, prefer services/repositories, and inject dependencies via constructors.
- **Blade**: Escape all dynamic output with `{{ }}` unless intentionally rendering safe HTML. Use components instead of inline scripts.
- **JavaScript/TypeScript**: Favor functional React components, keep hooks pure, and follow the ESLint/Prettier configuration bundled with Vite.
- **Markdown/Docs**: Keep prose concise, use sentence case for headings, and include relative links to local assets.

## Becoming a Maintainer
Consistent, high-quality contributions unlock elevated permissions. Demonstrate:
- Ownership of features from proposal through rollout.
- Responsiveness in reviews and adherence to our standards.
- Proactive work on documentation, CI, and security hardening.

If you are interested, reach out through the maintainer contact listed in `docs/SECURITY-OVERVIEW.md` or via the project discussion board.
