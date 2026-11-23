# Member feature permissions: Browse, Downloads, RSS and Watch Center

Hermes audit item 5 identified a gap between Sysop-managed upload/forum/message/invitation permissions and the release discovery, download and subscription surfaces. This change uses the **existing** `PermissionService`, per-role `site_settings` matrix (`access.roles.<role>`), per-member `user_permission_overrides`, and audit flow. No new authorization data store is introduced.

## Four permissions and effective prerequisites

| Sysop checkbox | Permission key | Protected surfaces |
| --- | --- | --- |
| Browse releases and saved views | `torrent.browse` | Browse listing and details on web/API, member discovery aggregates, dashboard release lists, saved views, follows |
| Download torrents | `torrent.download` | Personalized .torrent delivery on web/API and RSS token download |
| RSS feeds and presets | `rss.access` | Personal feed/token/preset administration, RSS feed URLs, RSS download URLs and RSS suggestion API |
| Search alerts and watch presets | `watch.access` | Watch Center, watch preset CRUD, watch suggestion API, background watch notification matching, and watch alert visibility in the combined inbox |

**Dependencies are enforced server-side in `PermissionService::allow`:**

- Download requires Browse.
- RSS requires both Browse and Download.
- Watch Center requires both Browse and Download.
- An individual `allow` override does **not** bypass a denied prerequisite. An individual deny takes priority over the role default for that same permission.
- Sysop retains managed feature access, while suspended/disabled users remain blocked by account policy.

These are **feature-access decisions**, separate from ratio, HnR, torrent approval/visibility, uploader ownership, and staff moderation rules. An allowed download feature does not defeat the existing ratio or content-visibility checks. Admin/staff moderation authorization remains role protected; member permissions do not grant administrative operations.

## Backward compatibility and default access

All seven existing member classes, including Newbie, have these four permissions enabled by **default**, matching access that existed before the Sysop checkboxes were added. The guest role is unchanged. Existing `access.roles.*` JSON rows that lack these new keys fall back to the declared defaults. Older four-checkbox Sysop form submissions preserve previous new-key settings rather than resetting them; the updated Sysop UI submits all checkboxes. Sysop edits continue to require audit reasons and retain the existing audit log.

Existing RSS tokens, saved views, watch presets and historical notification rows are preserved when access is revoked. They become inaccessible/hidden while the relevant permission is denied; restoring access restores the same stored data. Rotating a token is not required to revoke it: each bearer-token RSS request resolves the user and checks live permissions. Revoked token requests respond 404 without exposing the account.

## Enforced entry points

- Web Browse/detail/download; API Browse/detail/download; regular member-facing discovery home/metadata/popular/trending/summary.
- Authenticated RSS settings/presets, Watch Center/watch presets, follows and saved views, including direct POST/GET endpoints.
- RSS tokens for raw feeds, saved presets and personalized download payloads. All three respect current permissions even when the token remains valid.
- Watch matching jobs re-check effective permissions per member on execution. Historical watch notifications are not exposed by the combined notification inbox while access is denied; PM notifications remain available.
- Main navigation, account navigation, Browse watch/RSS links, Browse download buttons, and dashboard release lists match effective access. Navigation is not the security boundary.

## Torrent-client scope

These gates govern **new .torrent file delivery**, RSS ingestion, and release/notification visibility. They intentionally do not change tracker announce/scrape processing for already-held .torrent payloads: existing clients must still be able to announce and seed existing torrents, including HnR recovery. A policy for blocking *new* leeching via announce after rights are revoked requires a separate explicitly reviewed protocol change, not an implicit side effect of web feature permissions.

## Deployment

No database migrations or frontend asset build are required. After a green PR merge on the HP test machine:

```bash
cd ~/nextgn_tracker
git pull --ff-only origin main
php artisan optimize:clear
```

Validate the Sysop role and individual exception controls, the Browse navigation for a test member, and RSS with an existing token after a temporary permission change.
