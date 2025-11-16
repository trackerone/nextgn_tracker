# Security Checklist

Use this list before shipping changes or deploying NextGN Tracker.

## Configuration
- [ ] `.env` contains production credentials only (no defaults). Disable `APP_DEBUG` and set `APP_ENV=production`.
- [ ] Rotate `APP_KEY`, JWT secrets, and tracker passkey salts if compromise is suspected.
- [ ] Queue, cache, and session drivers point to Redis or database hosts reachable only inside the VPC.

## Application
- [ ] All routes that mutate state include CSRF protection (`web` middleware) and authorization (`role.min`/policies).
- [ ] Tracker endpoints validate passkeys, enforce HTTPS, and log rate-limit violations with user + IP context.
- [ ] User-supplied markdown is sanitized via `MarkdownService`; no raw HTML rendering without purification.
- [ ] No controllers access the database via raw mysqli/globals—only Eloquent, Query Builder, or Extended/Fluent PDO wrappers.

## Headers & TLS
- [ ] CSP, HSTS, Referrer-Policy, Permissions-Policy, and X-Frame-Options headers are enabled via middleware or web server config.
- [ ] Cookies (`SESSION`, `XSRF-TOKEN`, passkey cookies) include `Secure`, `HttpOnly`, and `SameSite=strict` attributes.
- [ ] ACME/Let’s Encrypt cert renewals are automated; TLS 1.0/1.1 are disabled.

## Logging & monitoring
- [ ] Sensitive fields (passkeys, info hashes, IPs) are redacted before logs are shipped off-host.
- [ ] Failed logins, announce denials, and moderation actions emit structured logs for the SIEM.
- [ ] Health checks hit `/health` or `/up` without bypassing authentication for privileged routes.

## Deployment
- [ ] Run `composer install --no-dev`, `npm ci`/`npm install`, `npm run build`, and `php artisan migrate --force` in CI/CD.
- [ ] Execute PHPStan, Rector (dry-run), PHPUnit, and frontend lint/tests before tagging a release.
- [ ] Warm caches post-deploy: `php artisan config:cache route:cache view:cache event:cache`.
