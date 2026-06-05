# Security Checklist

Use this checklist before public deployment.

For full hardening explanations and remediation flow, see [security/production-hardening.md](security/production-hardening.md).

## Configuration

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, valid `APP_KEY`, correct `APP_URL`.
- [ ] `NEXTGN_PRODUCTION_HARDENING=true` is set.
- [ ] `API_REQUIRE_NONCE=true` is enabled for nonce-based HMAC replay protection.
- [ ] `SECURITY_API_ALLOW_LEGACY_KEYS=false` in production.
- [ ] Queue/cache/session/filesystem drivers are intentional for production.

## Application

- [ ] Mutating routes have CSRF + authorization controls.
- [ ] API routes remain in documented session-auth/HMAC-auth boundaries.
- [ ] Tracker announce/scrape validates passkeys and does not leak restricted state.
- [ ] Scrape enforces maximum 50 `info_hash` values per request.
- [ ] Uploads flow only through shared validation/action pipeline.
- [ ] Metadata reads use `TorrentMetadataView`.

## Deployment and validation

- [ ] `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
- [ ] `npm ci && npm run build`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan nextgn:production-check`
- [ ] `GET /health` returns status OK without privileged details.

## Logs, TLS, and secrets

- [ ] TLS termination is enforced.
- [ ] Security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) are active.
- [ ] Passkeys, API keys, HMAC secrets, provider tokens, and plaintext passwords are never logged.
- [ ] Audit/security logs reviewed after sensitive changes.
