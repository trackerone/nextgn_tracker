# Sysop settings register and source-of-truth rules

This is the settings-infrastructure slice recommended by the Hermes Sysop audit. It **does not** migrate all stored values or add a second Sysop screen.

## Source and precedence

| Area | Persisted source | Default source | Cache | Owner |
| --- | --- | --- | --- | --- |
| Registration mode, tracker ratio/download rules, metadata provider switches and enrichment | `site_settings` | `SettingsRegistry` | No cache | Sysop |
| Announce interval, ghost-peer timeout and scrape limit | `site_settings` | Existing `config/tracker.php` via `SettingsRegistry` | No cache | Sysop |
| Upload file and NFO limits | `site_settings` | Existing `config/upload.php` via `UploadLimits::DEFINITIONS` | No cache | Sysop |
| HnR controls | `site_settings` | Existing `HnrSettings::DEFAULTS` | No cache | Sysop |
| Job toggles | `site_settings` | Existing `config/runtime_jobs.php` | No cache | Sysop (noncritical); server (critical) |
| Earned member-class ratio and tenure thresholds | **Legacy `settings`** | `config/ratio.php` | `settings.ratio`, cleared on edit | Staff/admin for ratios; Sysop for tenure |

`SettingsRegistry::definition(key)` describes the current owner, datatype, effective default, bounds or allowed values, default source, store and cache. `SettingsRegistry::legacyClassThresholds()` explicitly records the separate legacy thresholds without claiming that they have moved. There are no schema changes and **no new database rows** solely for defaults.

**For registered site keys, a valid persisted override always takes precedence.** When no override exists, the registry's default applies. For tracker and upload limits, the registry resolves the existing Laravel configuration fallback. Wrongly typed, malformed or out-of-range values fall back to a declared default rather than becoming false, zero, or an unexpected registration mode. In particular, invalid `tracker.ratio.enforcement_enabled` must never disable enforcement accidentally.

`SiteSettingsRepository::getMany()` remains a **raw batched override reader** for HnR and upload-limit clients. It does not materialize defaults. Typed getters read directly from `site_settings` on every call, so Sysop changes take effect on subsequent requests without worker restarts or application cache flushing. Class thresholds continue using their existing separate cache strategy, documented above.

## What this PR deliberately does not change

- The `settings` table, `RatioSettings` class, its staff/admin screens or existing class thresholds. Those need an isolated data migration plus cache-invalidation, rollback and authorization tests before the table can be retired.
- The HnR and upload-limit batch parsers or their existing limits.
- Credential storage: sensitive metadata secrets remain encrypted in `site_settings` through `MetadataCredentialsRepository`, not the ordinary settings reader.
- Actual worker/scheduler processes and server-owned settings, which cannot be enabled merely by a website toggle.

## Upgrade acceptance

The feature tests check registered definitions, source precedence, invalid-value fallbacks, immediate invalidation, runtime config fallback, raw batch behavior, and unchanged legacy ratio-threshold storage. Existing tests still cover Sysop-only mutations and their audit trails. No data migration is needed; do not reset the database or override owner-defined settings during deployment.

**Next isolated step:** consolidate the legacy member-class threshold writer/cache and its authorization through a dedicated audited service and backfilled migration, not by silently changing the reading order.
