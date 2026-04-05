# Torrent Backend – Slice 2 Closeout Notes

## Scope
This note documents the backend contracts and guardrails stabilized in Slice 2, so Slice 3 can build on consistent lifecycle, authorization, and API behavior.

## 1) Lifecycle states and transitions
`App\Enums\TorrentStatus` is the canonical lifecycle type:
- `pending`
- `published`
- `rejected`
- `soft_deleted`

Transition rules currently enforced by lifecycle actions:
- `pending -> published` via `PublishTorrentAction`
- `pending -> rejected` via `RejectTorrentAction`
- repeated moderation on already moderated uploads is invalid (`InvalidTorrentStatusTransitionException`)

Operational expectations:
- Public browse/details/download require a published+approved torrent.
- Uploaders can still access their own non-published uploads where policy allows it.
- Moderation metadata (`moderated_by`, `moderated_at`, optional reason) is set as part of moderation actions.

## 2) Authorization model
Authorization centers on `TorrentPolicy` abilities:
- Viewer-facing: `view`, `download`
- Moderation-facing: `viewModerationListings`, `viewModerationItem`, `publish`, `reject`, `moderate`

Key model:
- Staff (`User::isStaff()`) can access moderation surfaces.
- Non-staff access to hidden torrents uses not-found deny paths to avoid data leakage.
- Route-level staff middleware and policy checks are both retained (defense in depth).

## 3) Validated browse/moderation input boundaries
Form Requests define boundaries per endpoint type:
- `BrowseTorrentsRequest`: browse sorting/filtering/pagination only.
- `ModerationUploadsIndexRequest`: moderation listing filter (`status`) only.
- `ApiModerateTorrentRequest`: API moderation reject reason (nullable).
- `ModerateTorrentRequest`: web moderation reject reason (required for operator UX).

`ModerationUploadsIndexRequest` validates against `TorrentStatus::values()` to keep enum and request validation in sync.

## 4) API resource contract expectations
Slice 2 API resources are intentionally task-specific and stable:
- `TorrentBrowseResource`: list payload for browse pages.
- `TorrentDetailsResource`: full details + derived URLs.
- `ModerationTorrentResource`: compact moderation listing payload.
- `UploadSubmissionResource`: upload acknowledge payload.
- `MyUploadResource`: uploader visibility payload.
- `TorrentStatusResource`: minimal moderation action response payload.

Closeout principle:
- Keep key names and shape stable unless an explicit API contract change is planned.
- Prefer additive changes over renames in active clients.

## 5) Deny-path and throttling principles
Deny-path behavior:
- Policy denial for sensitive torrent access returns not-found style responses.
- `AuthorizationException` reporting maps torrent route contexts to security audit actions, including:
  - `torrent.access.denied_details`
  - `torrent.access.denied_download`
  - `torrent.moderation.unauthorized`

Throttling behavior:
- Torrent API/web endpoints use explicit per-surface rate-limit keys.
- `security.rate_limits.torrent_moderation` is the preferred moderation key.
- Legacy `security.rate_limits.moderation` remains a fallback for backward compatibility.

## 6) Closeout guardrails for Slice 3
- Reuse lifecycle actions for moderation state transitions.
- Reuse policy abilities rather than embedding ad-hoc authorization checks.
- Keep Form Requests single-purpose and endpoint-specific.
- Preserve established resource contracts unless versioning or migration is explicit.
- Keep security/audit event names domain-scoped (`torrent.*`, `auth.*`, `admin.*`) and consistent.
