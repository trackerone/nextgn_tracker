# Production Hardening

This guide documents the production security controls for NextGN and how to validate them before public exposure.

## Required baseline

Set these environment values in production:

```env
APP_ENV=production
APP_DEBUG=false
NEXTGN_PRODUCTION_HARDENING=true
```

## Security controls

- **Production hardening toggle**: `NEXTGN_PRODUCTION_HARDENING=true` enables production-hardening readiness expectations.
- **HMAC nonce replay protection**: API HMAC requests require `X-Api-Nonce` when `API_REQUIRE_NONCE=true`, and nonce reuse for the same API key is rejected.
- **Legacy API key compatibility enforcement**: `SECURITY_API_ALLOW_LEGACY_KEYS=true` can be used for staged migration, but production should set it to `false`.
- **Scrape info_hash hard cap**: scrape requests are capped at **50** `info_hash` parameters per request.
- **Production-safe defaults**: nonce enforcement defaults to enabled and allowed HMAC skew defaults to 120 seconds.

## Validate with production-check

Run:

```bash
php artisan nextgn:production-check
```

The command validates:

- production hardening enabled
- `APP_ENV=production`
- `APP_DEBUG=false`
- HMAC nonce enforcement enabled
- legacy API keys disabled
- acceptable HMAC skew window (120 seconds or less)
- no remaining legacy plaintext API keys

If any check fails, fix configuration and key state before public exposure.

## Migration note

Legacy API key compatibility exists to support staged rollout. For production deployment, disable legacy keys (`SECURITY_API_ALLOW_LEGACY_KEYS=false`) before exposing the service publicly.
