# Security checklist

Use this list before shipping changes or deploying NextGN Tracker.

## Configuration

- [ ] Production has `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, correct `APP_URL`, and database credentials supplied by the platform.
- [ ] API HMAC settings are present when API-key routes are used.
- [ ] Queue, cache, session, and filesystem drivers are intentional for the environment.

## Application

- [ ] Mutating web routes use CSRF protection and authorization through middleware, policies, or gates.
- [ ] API routes stay in their documented session-auth or HMAC-auth buckets.
- [ ] Tracker announce/scrape routes validate passkeys and do not leak restricted torrent state.
- [ ] Uploads go through `SubmitTorrentUploadAction` and the shared validation rules; no alternate upload path bypasses MIME/extension/size checks.
- [ ] Torrent metadata reads go through `TorrentMetadataView`.
- [ ] User-supplied text is sanitized or escaped before rendering.

## Headers, TLS, and logs

- [ ] CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and related security headers are active.
- [ ] TLS termination is enforced at the platform/load balancer.
- [ ] Passkeys, API keys, HMAC secrets, provider tokens, raw announce IDs, and plaintext passwords are not logged.
- [ ] Admin audit/security views and the `security` log are reviewed after sensitive changes.

## Deployment

- [ ] Run `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` for production builds.
- [ ] Run `npm ci` or `npm install` and `npm run build` when frontend assets change.
- [ ] Run `php artisan migrate --force` before serving traffic.
- [ ] Rebuild Laravel caches with config, route, and view cache commands or the container entrypoint.
- [ ] Verify `GET /health` returns `{ "status": "ok" }` without exposing privileged data.
