# Sysop settings registry and source-of-truth rules

This document describes the staged Hermes settings convergence. PR #614 registered the source, type, bounds and fallback for settings without moving values. The following class-threshold migration moves the final legacy **active** class-settings reads and writes to the same canonical `site_settings` store.

## Effective sources

| Area | Persisted override | Default source | Cache | Owner |
| --- | --- | --- | --- | --- |
| Registration, download eligibility, metadata provider switches and enrichment | `site_settings` | `SettingsRegistry` | No cache | Sysop |
| Tracker announce, ghost-peer timeout and scrape caps | `site_settings` | `config/tracker.php` | No cache | Sysop |
| Upload torrent/NFO limits | `site_settings` | `UploadLimits::DEFINITIONS` | No cache | Sysop |
| HnR controls | `site_settings` | `HnrSettings::DEFAULTS` | No cache | Sysop |
| Runtime jobs | `site_settings` | `config/runtime_jobs.php` | No cache | Sysop for noncritical, server for critical |
| **Earned member-class ratio and tenure thresholds** | **`site_settings`** | `config/ratio.php` via `SettingsRegistry` | Per `RatioSettings` instance only | Admin for ratios; Sysop for tenure |

The `SettingsRegistry::definition(key)` defines type, default, owner, limits and source. `SettingsRegistry::classThresholds()` lists the eight member-class keys. A valid `site_settings` override wins over the registered default. Malformed or out-of-range values fall back to defaults instead of silently becoming false or zero.

`RatioSettings` reads the eight class threshold overrides with one query and caches the resolved values only within its service instance. On the next HTTP request, a fresh service reads the current values. After updating thresholds within the same request, its `flush()` resets only that in-memory instance. No `settings.ratio` global cache is used by current class promotion reads.

**Critical separation:** `ratio.*` class thresholds affect earned display classes (Newbie, User, Power User and Elite). They do not confer staff permissions and do **not** change `tracker.ratio.*` torrent download restrictions.

## Safe migration and rollback

The migration `2026_11_22_000500_backfill_class_thresholds_into_site_settings` backfills only the eight known member-class keys from the legacy `settings` table. It:

- Copies only valid, in-range, typed legacy values.
- Never overwrites an already-present `site_settings` value.
- Leaves old `settings` rows intact as a rollback source, but stops using them for active class-threshold reads and writes.
- On rollback, copies current canonical values **back to legacy** so owner changes made after migration are not lost. It does not delete canonical values.
- Clears the old `settings.ratio` cache key on migration and rollback.

The legacy table is deliberately **not dropped** in this PR because dropping it could destroy unrelated historical data and complicate application rollback. Removing the table requires a separate usage inventory and explicit data-retention decision.

Admin/Sysop class-threshold edits keep existing role authorization, the tenure-only Sysop restriction and the existing audit event. Threshold writes plus their audit record are transactional. The unified Sysop page and staff administration surface remain unchanged.

## Upgrade

On the HP test server, once CI and review are green:

```bash
git pull --ff-only origin main
php artisan migrate --force
php artisan optimize:clear
```

No frontend rebuild is necessary. Do not reset the database. Validate a member's effective class thresholds and the separately configured global download ratio afterward. Do not merge or deploy while tests or review are unresolved.
