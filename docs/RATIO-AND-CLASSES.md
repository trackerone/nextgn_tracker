# Torrent visibility, ratios & announce enforcement

## Torrent flags
- Public torrent listings require published, approved, visible and non-banned status. Moderation access is separately authorized.
- `is_freeleech` is the single authoritative freeleech state in the database. Freeleech releases do not credit downloaded bytes to ratio, while verified uploaded bytes still count. Sysop controls whether freeleech can bypass minimum download ratio; this is separate from HnR obligations, which continue to count physical transferred bytes.

## Effective ratio
The `UserRatioBalance` service is the shared source for the effective uploaded/downloaded totals and ratio. It uses integrity-credited traffic (falling back to historical `user_torrents` data where needed) plus separately audited Sysop ratio credits. The user's `ratio()`, dashboard, account pages, download eligibility and now new-leech-start announce checks use the same effective balance.

## Separate owner-configurable policies
**Download eligibility** is controlled by Sysop at **Site settings → Download ratio rules**:
- `tracker.ratio.enforcement_enabled` (default on)
- `tracker.ratio.minimum_download_ratio` (default 0.50)
- `tracker.ratio.freeleech_bypass_enabled` (default on)
- `tracker.ratio.no_history_grace_enabled` (default on)

The same `DownloadEligibilityPolicy::checkRatio()` decision applies to a personalized .torrent download and the tracker when a **non-staff leecher** announces `event=started` with bytes left to download. The separate HnR check runs before announce ratio enforcement; HnR recovery is unaffected. Seeders, staff, and continuing/stopping announces are not blocked by the start-only ratio guard.

**Earned member classes** are separate from download eligibility. Their ratio, traffic and tenure thresholds are configured at **Roles, permissions, ratio & balances**; defaults live in `config/ratio.php`. Class thresholds must never be treated as download thresholds, and changing them must not silently change announce restrictions.

## Sysop control and audit
Only the authenticated Sysop web settings form can write the tracker ratio policy. Saving requires a reason, validates safe bounds and the relationship between announce interval and peer timeout, and commits all changed settings with an audit record in one transaction.

`GET /api/admin/settings/tracker/ratio` remains a **read-only** authorized inspection endpoint. Its legacy POST mutation was removed before the first public release so it cannot bypass the Sysop audit trail.

Saved values take effect on subsequent requests without redeploying code. The code contains no separate hardcoded announce ratio threshold.

## Freeleech schema consolidation (before first public release)

A one-time migration (`2026_11_22_210000_consolidate_torrent_freeleech`) copies any legacy true values into `is_freeleech` and drops the old `freeleech` database column. This protects already-seeded test data; the application, RSS, Browse, API and tracker accounting now use only `is_freeleech`.

The outward API response field and RSS filter parameter remain named `freeleech` for clarity; **they are not database fields**. Existing HnR obligations continue to use validated physical bytes regardless of freeleech ratio credits. Changes to future HnR policy should remain a separate explicit Sysop choice, not a side effect of this cleanup.

Run `php artisan migrate --force` on the HP test server after updating. No asset rebuild is required for this database/PHP change.
