# Torrent visibility, ratios & announce enforcement

## Torrent flags
- Public torrent listings require published, approved, visible and non-banned status. Moderation access is separately authorized.
- `is_freeleech` / legacy `freeleech`: the existing download policy recognizes these flags; the Sysop-configured freeleech bypass can exempt the minimum ratio.

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
