NextGN Tracker
<p align="center"> <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" /> <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" /> <img src="https://img.shields.io/badge/Node-20--24_LTS-339933?style=flat-square&logo=node.js&logoColor=white" /> <img src="https://img.shields.io/badge/Vite-Assets-646CFF?style=flat-square&logo=vite&logoColor=white" /> <img src="https://img.shields.io/badge/License-MIT-3B82F6?style=flat-square" /> <br/> <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml"> <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php.yml/badge.svg" /> </a> <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend.yml"> <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend.yml/badge.svg" /> </a> <img src="https://img.shields.io/badge/Pint-Passing-22C55E?style=flat-square" /> <img src="https://img.shields.io/badge/Larastan-Passing-16A34A?style=flat-square" /> <img src="https://img.shields.io/badge/Tests-Passing-4ADE80?style=flat-square" /> </p>
Overview

NextGN Tracker is a next-generation tracker-style web application built on modern Laravel.
The project is designed from the ground up with no legacy constraints, strict quality gates, and a fully automated CI pipeline.

The main branch is always expected to be stable and deployable.

Technology Stack
Backend

Laravel 12

PHP 8.3+

Pest (testing)

PHPStan / Larastan (static analysis)

Laravel Pint (code style)

Frontend

Vite 5

React 18

TypeScript

Tailwind CSS

Runtime

Node.js 20–24 LTS

MySQL / MariaDB (default)

Quality & CI

All changes are validated automatically through CI:

Code style enforcement via Laravel Pint

Static analysis via Larastan / PHPStan

Automated test suite using Pest

Frontend production build using Vite

No merges are accepted unless all checks pass.

Local Installation

git clone https://github.com/trackerone/nextgn_tracker.git

cd nextgn_tracker

cp .env.example .env

composer install
npm install

php artisan key:generate
php artisan migrate

php artisan serve
npm run dev

Run CI Checks Locally

composer lint
composer analyse
composer test
npm run build

Development Principles

No legacy code

PHP 8.3+ only

Strict typing

CI is the source of truth

main is always release-ready

Contributing

All contributions must comply with the established architecture, tests, and quality standards.

See CONTRIBUTING.md for details.

Documentation

docs/SECURITY-OVERVIEW.md

docs/SECURITY-CHECKLIST.md

docs/STACK-BASELINE.md

docs/FRONTEND-SETUP.md

Deployment Notes

Configure environment variables, cache, queues, and storage

Run migrations with:
php artisan migrate --force

Build frontend assets with:
npm run build

Point the web server document root to /public

Ensure write access to storage/ and bootstrap/cache
