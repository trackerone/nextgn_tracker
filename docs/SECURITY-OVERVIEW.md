# Security Overview

NextGN Tracker assumes hostile network conditions and enforces layered controls across the stack. This document highlights the key expectations for contributors and operators.

## Application surface
- **Authentication** – Email/password with bcrypt hashing and optional passkey/WebAuthn support. Sessions are CSRF-protected, rate limited, and require verified email before tracker endpoints can be used.
- **Roles & permissions** – `sysop`, `admin`, `moderator`, `uploader`, `user`, and `guest` roles drive authorization gates/policies. `role.min:{level}` middleware is enforced for staff panels, uploads, and moderation flows.
- **Tracker endpoints** – `/announce` and `/scrape` require a valid passkey tied to the authenticated user plus request signing (HMAC) for API integrations. Requests are throttled per user/IP and responses are sanitized through the bencode service.
- **API clients** – Any automation must authenticate via personal access tokens or signed URLs; never expose passkeys in logs or front-end bundles.

## Transport & headers
- Enforce HTTPS everywhere (HSTS, TLS 1.2+). Redirect HTTP to HTTPS at the load balancer or CDN edge.
- Apply CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy, and X-Content-Type-Options headers globally (Laravel middleware + web server defaults).
- Enable SameSite=strict cookies and secure flags for session + XSRF tokens.

## Data protection
- Use prepared statements via Eloquent/Query Builder only (no raw mysqli). Avoid storing plaintext IPs long term—truncate or hash where possible.
- Secrets live solely in `.env`/platform config. Never commit keys, passphrases, or certs to git.
- Logs must redact passkeys, tokens, info hashes, and IP addresses before shipping to external services.

## Operational safeguards
- Enable Laravel maintenance mode before migrations in production.
- Keep `composer.lock` and your Node lock file (`package-lock.json`, `pnpm-lock.yaml`, etc.) up to date via Dependabot or scheduled upgrades; run PHPStan, Rector, and PHPUnit before deploying.
- Rotate passkeys and API credentials immediately if compromise is suspected. Document incident responses in the ops runbook.
