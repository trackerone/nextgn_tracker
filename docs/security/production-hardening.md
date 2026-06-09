# Production Hardening

This guide documents required production safeguards and validation commands.

## Required baseline

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
TRACKER_ANNOUNCE_URL=https://your-domain.example/announce/%s
NEXTGN_PRODUCTION_HARDENING=true
API_REQUIRE_NONCE=true
SECURITY_API_ALLOW_LEGACY_KEYS=false
```

## HTTPS deployment requirement

Production must run behind HTTPS. Use a TLS-terminating reverse proxy, load balancer, or platform edge in front of the application runtime and expose only the HTTPS public origin to users and BitTorrent clients. Local `http://localhost` examples are development-only and should never be used as production `APP_URL` or `TRACKER_ANNOUNCE_URL` values.

`APP_URL` must be the canonical public HTTPS site origin. `TRACKER_ANNOUNCE_URL` must be the public HTTPS announce URL advertised in generated torrents, including the `%s` passkey placeholder for passkey-based announce routes.

## Hardened controls

- `NEXTGN_PRODUCTION_HARDENING=true` enables strict production expectations.
- Nonce-based HMAC replay protection is enabled with `API_REQUIRE_NONCE=true`.
- Legacy API keys should be disabled using `SECURITY_API_ALLOW_LEGACY_KEYS=false`.
- Scrape endpoints cap requests at **50** `info_hash` values per request.

## Validate deployment safety

Run after each production deployment or update:

```bash
php artisan nextgn:production-check
```

The check validates:

- production hardening enabled
- production environment flags
- HMAC nonce enforcement
- legacy key compatibility disabled
- acceptable HMAC skew window
- no remaining legacy plaintext API keys

## Fast fixes for common failures

### Production check fails on hardening toggle

```bash
php artisan config:clear
# set NEXTGN_PRODUCTION_HARDENING=true in env/secrets
php artisan config:cache
php artisan nextgn:production-check
```

### Production check fails on nonce requirement

```bash
php artisan config:clear
# set API_REQUIRE_NONCE=true
php artisan config:cache
php artisan nextgn:production-check
```

### Production check fails on legacy key allowance

```bash
php artisan config:clear
# set SECURITY_API_ALLOW_LEGACY_KEYS=false
php artisan config:cache
php artisan nextgn:production-check
```
