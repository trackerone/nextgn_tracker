# API Contract

The `packages/api-contract` workspace is the single source of truth for frontend TypeScript API types.

## Goals

- Keep shared DTOs in one place.
- Let frontend code import types directly via a workspace dependency.
- Reduce drift between backend JSON responses and frontend expectations.

## Usage

Import types from the workspace package:

```ts
import type { TorrentDto } from '@nextgn/api-contract';
```

As backend endpoints evolve, update the DTOs here first and then adjust frontend usage.

## Internal API authentication contract

API routes intentionally use two authentication modes. Keep new routes in one of these buckets and do not mix browser-session auth with HMAC auth on the same endpoint unless the route contract is explicitly changed and tested.

### Browser/session authenticated API routes

These routes are first-party application endpoints intended for the logged-in web UI and browser-backed JSON calls. They use the default `auth` middleware plus the API middleware stack, so unauthenticated callers receive `401` responses and banned or disabled users are blocked by `ActiveUserMiddleware` with `403` responses.

- `GET /api/torrents`
- `GET /api/torrents/{torrent}`
- `GET /api/torrents/{torrent}/download`
- `POST /api/uploads`
- `GET /api/my/uploads`
- `GET /api/me/stats`
- `GET /api/moderation/uploads` (also requires `staff`)
- `POST /api/moderation/uploads/{torrent}/approve` (also requires `staff`)
- `POST /api/moderation/uploads/{torrent}/reject` (also requires `staff`)
- `GET|POST /api/admin/settings/tracker/ratio` (also requires `role.level:admin`)
- `GET|POST /api/admin/settings/metadata/providers` (also requires `role.level:admin`)
- `GET|PUT|DELETE /api/admin/settings/metadata/credentials...` (also requires `role.level:admin`)

### HMAC/API-key authenticated API routes

HMAC routes are stateless API-client endpoints. Browser sessions must not satisfy this contract: callers must send `X-Api-Key`, `X-Api-Timestamp`, and `X-Api-Signature`. The signature is an HMAC-SHA256 over `METHOD`, canonical path, timestamp, and body separated by newlines. Timestamps must be fresh according to `security.api.allowed_time_skew_seconds`.

- `GET /api/user`

A valid API key authenticates as the key owner for the request. Banned or disabled key owners are rejected with `403`; missing credentials, unknown keys, stale timestamps, and invalid signatures are rejected with `401`.

### Stateless bearer-token clients

There is currently no separate bearer-token/Sanctum API route contract in `routes/api.php`. Add one only with explicit route grouping, middleware, documentation, and tests that prove it does not weaken the session or HMAC contracts above.
