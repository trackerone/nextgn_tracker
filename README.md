<h1 align="center">NextGN Tracker</h1>

<p align="center">
  <!-- CI -->
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-lint-pint.yml/badge.svg" alt="PHP Lint (Pint)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-static-analysis.yml/badge.svg" alt="PHP Static Analysis (Larastan)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-php-tests-pest.yml/badge.svg" alt="PHPUnit Test (Pest)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-vite.yml/badge.svg" alt="Compile Assets (Vite)" />
  </a>
  <a href="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-prettier.yml">
    <img src="https://github.com/trackerone/nextgn_tracker/actions/workflows/ci-frontend-prettier.yml/badge.svg" alt="Format (Prettier)" />
  </a>
</p>


<hr/>

<h2>Overview</h2>

<p>
  <strong>NextGN Tracker</strong> is a next-generation tracker platform built on modern Laravel.
  The project is <strong>legacy-free by design</strong>, strictly typed, and continuously validated through CI.
</p>

<p>
  The <code>main</code> branch is always expected to be <strong>green, stable, and deployable</strong>.
</p>

<hr/>

<h2>Technology Stack</h2>

<h3>Backend</h3>
<ul>
  <li>Laravel 12</li>
  <li>PHP 8.3+</li>
  <li>Pest (testing)</li>
  <li>PHPStan / Larastan (static analysis)</li>
  <li>Laravel Pint (code style)</li>
</ul>

<h3>Frontend</h3>
<ul>
  <li>Vite 5</li>
  <li>React 18</li>
  <li>TypeScript</li>
  <li>Tailwind CSS</li>
</ul>

<h3>Runtime</h3>
<ul>
  <li>Node.js 20–24 LTS</li>
  <li>MySQL / MariaDB (default)</li>
</ul>

<hr/>

<h2>Quality Gates (CI)</h2>

<p>All changes must pass the following automated checks:</p>

<ul>
  <li>Code style enforcement (Laravel Pint)</li>
  <li>Static analysis (Larastan / PHPStan)</li>
  <li>Feature &amp; unit tests (Pest)</li>
  <li>Frontend production build (Vite)</li>
</ul>

<p><strong>No merges without a fully green pipeline.</strong></p>

<hr/>

<h2>Local Installation</h2>

<pre><code>
git clone https://github.com/trackerone/nextgn_tracker.git
cd nextgn_tracker

cp .env.example .env

composer install
npm install

php artisan key:generate
php artisan migrate

php artisan serve
npm run dev
</code></pre>

<hr/>

<h2>Run CI Checks Locally</h2>

<pre><code>
composer lint
composer analyse
composer test
npm run build
</code></pre>

<hr/>

<h2>Development Principles</h2>

<ul>
  <li>No legacy code</li>
  <li>PHP 8.3+ only</li>
  <li>Strict typing everywhere</li>
  <li>CI is the single source of truth</li>
  <li><code>main</code> is always release-ready</li>
</ul>

<hr/>

<h2>Contributing</h2>

<p>
  All contributions must comply with the existing architecture, tests, and quality standards.
</p>

<p>
  See <code>CONTRIBUTING.md</code> for details.
</p>

<hr/>

<h2>Documentation</h2>

<ul>
  <li><code>docs/SECURITY-OVERVIEW.md</code></li>
  <li><code>docs/SECURITY-CHECKLIST.md</code></li>
  <li><code>docs/STACK-BASELINE.md</code></li>
  <li><code>docs/FRONTEND-SETUP.md</code></li>
</ul>

<hr/>

<h2>Deployment Notes</h2>

<pre><code>
php artisan migrate --force
npm run build
</code></pre>

<ul>
  <li>Web server document root must point to <code>/public</code></li>
  <li>Ensure write access to <code>storage/</code> and <code>bootstrap/cache</code></li>
  <li>Configure cache, queues, and environment variables appropriately</li>
</ul>
