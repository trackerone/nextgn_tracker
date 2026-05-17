# Security overview

NextGN Tracker assumes hostile network conditions and uses layered Laravel controls across the web UI, API, tracker protocol endpoints, uploads, and operations.

## Application surface

- **Authentication**: Email/password sessions are CSRF-protected and rate limited. Banned or disabled users are blocked by active-user middleware.
- **Roles and permissions**: Role level middleware, staff middleware, gates, and policies protect admin, moderation, forum, torrent, and log surfaces.
- **Tracker endpoints**: `/announce/{passkey}` and `/scrape/{passkey}` require a valid passkey for an active user. Announce requests are throttled, normalized, checked against torrent access rules, and answered with bencoded responses.
- **API clients**: First-party JSON routes use browser/session auth. The current stateless API contract is HMAC API-key auth for `GET /api/user` with `X-Api-Key`, `X-Api-Timestamp`, and `X-Api-Signature`.
- **Uploads**: Torrent/NFO uploads use centralized validation, MIME/extension/size limits, sanitization, duplicate detection, metadata persistence, and audit/security telemetry.

## Headers and transport

- `SecurityHeadersMiddleware` and `ResponseGuard` apply security headers such as CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and related response hardening.
- Terminate TLS at the platform/load balancer and run production with `APP_ENV=production` and `APP_DEBUG=false`.
- Keep session and XSRF cookies secure for production deployments.

## Data protection

- Use Laravel Eloquent, query builder, or existing repositories; do not add raw user-input SQL, mysqli, or global database handles.
- Store secrets only in environment/platform configuration. Never commit app keys, API keys, HMAC secrets, provider tokens, passkeys, or passwords.
- Do not log passkeys, API keys, HMAC secrets, provider tokens, raw announce IDs, or plaintext passwords. Security-sensitive events should go through the configured security log/audit surfaces.

## Operational safeguards

- Run migrations as an explicit release step before serving production traffic.
- Run queue workers and the scheduler as separate processes when queued jobs or scheduled commands are enabled.
- Rotate passkeys and API credentials if compromise is suspected, then review audit/security logs for related activity.
