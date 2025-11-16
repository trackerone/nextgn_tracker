<<<<<< codex/create-contributing.md-and-update-readme.md-mvu9hi
# Contributing to NextGN Tracker

<p align="center">

  <!-- Stack -->
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/Node-20--24_LTS-339933?style=flat-square&logo=node.js&logoColor=white" />
  <img src="https://img.shields.io/badge/Vite-Assets-646CFF?style=flat-square&logo=vite&logoColor=white" />
  <img src="https://img.shields.io/badge/License-MIT-3B82F6?style=flat-square" />

  <br/>

  <!-- CI -->
  <a href="https://github.com/YOUR_ORG/nextgn_tracker/actions/workflows/ci-php.yml">
    <img src="https://github.com/YOUR_ORG/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg" />
  </a>
  <a href="https://github.com/YOUR_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml">
    <img src="https://github.com/YOUR_ORG/nextgn_tracker/actions/workflows/ci-frontend.yml/badge.svg" />
  </a>

  <!-- Static cues -->
  <img src="https://img.shields.io/badge/Pint-Passing-22C55E?style=flat-square" />
  <img src="https://img.shields.io/badge/Larastan-Passing-16A34A?style=flat-square" />
  <img src="https://img.shields.io/badge/Tests-Passing-4ADE80?style=flat-square" />

</p>

> Replace `YOUR_ORG` with your GitHub organization or username so the workflow badges point to the correct repository.

## Introduction
NextGN Tracker is a hardened, Laravel 12-based tracker platform focused on privacy, compliance, and predictable performance. We welcome contributions that improve stability, security, developer experience, and the Vite-driven interface.

## Code of Conduct
All contributors must follow the [Code of Conduct](./CODE_OF_CONDUCT.md). Engage with kindness, accept feedback gracefully, and keep reviews focused on the work rather than the person.

## Getting Started
1. Review the [README](./README.md) for supported runtimes, tooling, and CI workflows.
2. Search existing issues and discussions to avoid duplicates and learn the current direction.
3. For non-trivial changes, open an issue or proposal outlining motivation, scope, and acceptance criteria before coding.
4. Coordinate with maintainers when touching security-sensitive paths or the deployment pipeline.

## Development Setup (PHP 8.3+, Laravel 12, Node 20–24 LTS, Composer, npm, Vite)
- Requirements: PHP 8.3+, Composer 2, Node 20–24 LTS, npm 9+, and a database supported by Laravel 12.
- Clone the repository, then run `composer install` and `npm install`.
- Copy `.env.example` to `.env`, set database credentials, queue/mail drivers, and tracker-specific keys.
- Run `php artisan key:generate`, `php artisan migrate`, and boot the stack with `php artisan serve` plus `npm run dev`.
- Use `npm run build` before submitting frontend work to ensure the Vite manifest stays valid.

## Branching Strategy & Workflow
- `main` mirrors production; all pull requests must target `develop` (or your fork's upstream integration branch).
- Prefix branches according to intent: `feature/<short-name>`, `bugfix/<short-name>`, or `hotfix/<short-name>`.
- Keep branches rebased on the latest `develop`: `git fetch origin && git rebase origin/develop`.
- Example flow:
  ```bash
  git checkout -b feature/better-announce
  git commit -m "Add announce endpoint metrics"
  git push origin feature/better-announce
  ```
- Keep commits atomic (<200 lines when possible) and write meaningful messages describing intent.

## Coding Standards (PSR-12, Pint, Larastan, Tests)
- Follow PSR-12, strict typing, and Laravel architectural conventions.
- Run `composer lint` (Laravel Pint) before every push; the codebase treats lint violations as blockers.
- Execute `composer analyse` (Larastan at high level) and resolve or justify all findings.
- Ensure `composer test` (Pest/PHPUnit) passes locally and add coverage for new behavior.
- Frontend changes must satisfy ESLint/Prettier configs and ship with `npm run build` proof.

## Security & Responsible Disclosure
- Never introduce raw SQL that touches user input; use the query builder or parameterized statements.
- All uploads must go through the centralized upload pipeline with antivirus and MIME validation enabled.
- Escape or sanitize every piece of user-generated content using ContentSafety helpers before rendering.
- Report vulnerabilities privately using the contact in `docs/SECURITY-OVERVIEW.md` before disclosing publicly.
- When fixing vulnerabilities, include regression tests and describe risk/mitigation clearly in the PR.

## Submitting Pull Requests
1. Rebase on the latest `develop` and confirm your branch builds cleanly.
2. Run the full toolchain locally: `composer lint`, `composer analyse`, `composer test`, and `npm run build`.
3. Update docs or changelogs when behavior changes.
4. Push your branch and open a PR against `develop`, filling out the template and referencing related issues.
5. PRs must stay green on both **CI - PHP** and **CI - Frontend** workflows; maintainers will not merge red builds.

## Style Guidelines (PHP, Blade, JS, Markdown)
- **PHP**: Prefer constructor injection, typed properties, and small service classes. Avoid facades in domain logic. Keep methods focused and document complex decisions inline.
- **Blade**: Escape everything with `{{ }}` by default, leverage components/slots, and keep inline scripts minimal. Use `@vite` helpers for assets.
- **JavaScript/TypeScript**: Use functional components with hooks, keep state localized, and follow the repo ESLint/Prettier setup. Import icons from `lucide-react` and shared UI from shadcn components.
- **Markdown**: Use sentence case headings, keep paragraphs short, and favor relative links for local references.

## Becoming a Maintainer
We invite new maintainers from the pool of consistent contributors who:
- Deliver well-tested, documented features end-to-end.
- Participate actively in reviews and triage.
- Demonstrate ownership of security, performance, and accessibility concerns.

If you're interested, contact the current maintainers via the channel listed in `docs/SECURITY-OVERVIEW.md` or start a discussion outlining your contributions and availability.
=======
![Contributions](https://img.shields.io/badge/Contributions-Welcome-22C55E?style=for-the-badge&logo=github)
![Stack](https://img.shields.io/badge/Stack-Laravel_12,_PHP_8.3%2B,_Vite-2563EB?style=for-the-badge)
[![CI - PHP](https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg)](https://github.com/trackerone/nextgn_tracker/blob/main/.github/workflows/ci-php.yml)
[![CI - Frontend](https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend.yml/badge.svg)](https://github.com/trackerone/nextgn_tracker/blob/main/.github/workflows/ci-frontend.yml)

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
>>>>>> main
