# Slice 3C.1 — Upload + Moderation flow map (as-is)

## Scope
- Mapped current upload and moderation flow end-to-end.
- Focused on authorization, state transitions, and audit/telemetry seams.
- No behavior changes.

## Exact files inspected
- `routes/web.php`
- `routes/api.php`
- `bootstrap/app.php`
- `app/Http/Controllers/TorrentUploadController.php`
- `app/Http/Controllers/Api/UploadSubmissionController.php`
- `app/Http/Controllers/TorrentModerationController.php`
- `app/Http/Controllers/Api/ModerationUploadsController.php`
- `app/Http/Controllers/MyUploadsController.php`
- `app/Http/Controllers/Api/MyUploadsController.php`
- `app/Http/Requests/StoreTorrentRequest.php`
- `app/Http/Requests/TorrentUploadRequest.php`
- `app/Http/Requests/ModerateTorrentRequest.php`
- `app/Http/Requests/ApiModerateTorrentRequest.php`
- `app/Http/Requests/ModerationUploadsIndexRequest.php`
- `app/Policies/TorrentPolicy.php`
- `app/Http/Middleware/EnsureUserIsStaff.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Models/Torrent.php`
- `app/Models/User.php`
- `app/Enums/TorrentStatus.php`
- `app/Actions/Torrents/PublishTorrentAction.php`
- `app/Actions/Torrents/RejectTorrentAction.php`
- `app/Services/Torrents/TorrentIngestService.php`
- `app/Services/Logging/AuditLogger.php`
- `app/Models/SecurityAuditLog.php`
- `app/Http/Resources/UploadSubmissionResource.php`
- `app/Http/Resources/MyUploadResource.php`
- `app/Http/Resources/ModerationTorrentResource.php`
- `app/Http/Resources/TorrentStatusResource.php`
- `tests/Feature/TorrentUploadTest.php`
- `tests/Feature/TorrentModerationFlowTest.php`
- `tests/Feature/Torrent/TorrentOperationalHardeningTest.php`

## End-to-end flow (current)

### Upload (web)
1. `GET /torrents/upload` calls `TorrentUploadController@create` and runs `authorize('create', Torrent::class)`.
2. `POST /torrents` calls `TorrentUploadController@store` with `StoreTorrentRequest` validation.
3. Controller ingests payload via `TorrentIngestService`, which always persists as `status=pending`, `is_approved=false`, `published_at=null`.
4. Upload writes both domain audit (`AuditLogger` → `audit_logs`) and security telemetry (`SecurityAuditLog` → `security_audit_logs`).

### Upload (API)
1. `POST /api/uploads` calls `UploadSubmissionController@store` with `StoreTorrentRequest`.
2. Controller ingests through the same `TorrentIngestService`.
3. Returns minimal resource (`id`, `slug`, `name`, `status`).

### Moderation (web)
1. Staff routes are under `auth + staff + throttle` and handled by `TorrentModerationController`.
2. `index` authorizes `viewModerationListings` and lists pending/moderated items.
3. `approve` authorizes `publish`, then uses `PublishTorrentAction`.
4. `reject` authorizes `reject`, then uses `RejectTorrentAction`.
5. `softDelete` authorizes `moderate`, then writes status directly in controller.
6. Moderation writes both `audit_logs` and `security_audit_logs`.

### Moderation (API)
1. API moderation routes are `auth + throttle` (no `staff` middleware), then policy authorization inside controller.
2. `approve/reject` explicitly catch authorization exceptions to log `torrent.moderation.unauthorized`.
3. Status transitions run through `PublishTorrentAction`/`RejectTorrentAction` and reject invalid transitions with `422` + telemetry.

## Who can do what (today)

### Who can upload
- Effective gate in policy: `TorrentPolicy::create` allows users that are **not banned** and **not disabled**.
- But enforcement is inconsistent:
  - Web `create` form checks this policy.
  - Web `store` and API `store` do not re-check policy; they only require authenticated user via route/request.

### Who can approve/moderate
- `TorrentPolicy::{viewModerationListings,publish,reject,moderate}` all defer to `canAccessModeration()`.
- `canAccessModeration()` is `User::isStaff()`.
- Staff determination is duplicated across:
  - `User::isStaff()` (flag + role level + role relation + slug fallback)
  - `EnsureUserIsStaff` middleware (its own layered checks + fallback to `isStaff()`).

## Torrent states and transitions
- Enum states: `pending`, `published`, `rejected`, `soft_deleted`.
- Ingest always creates `pending`.
- `PublishTorrentAction` allows only `pending -> published`.
- `RejectTorrentAction` allows only `pending -> rejected`.
- `softDelete` currently sets `soft_deleted` directly in controller without shared transition action.
- Visibility/display is effectively tied to `published + is_approved + not banned`.

## Duplicated / implicit rule hotspots
1. **Upload eligibility split across entry points**: policy checked on web form page but not guaranteed on submit/API submit.
2. **Staff/moderation eligibility duplicated**: middleware + policy + controller exception logging + global auth exception logging.
3. **State mutation split**: approve/reject use dedicated actions; soft delete mutates inline in controller.
4. **Legacy dual-state semantics**: `status` + `is_approved` both influence visibility/approval, requiring coordinated writes.

## Existing logging/audit
- `AuditLogger` events: `torrent.created`, `torrent.approved`, `torrent.rejected`, `torrent.soft_deleted`.
- `SecurityAuditLog` events include:
  - upload failures/duplicates/success (`torrent.upload.*`),
  - moderation unauthorized (`torrent.moderation.unauthorized`),
  - invalid transitions (`torrent.moderation.invalid_transition`),
  - denied access telemetry paths already aligned from Slice 3B.

## Seams for extraction (3C.2)
1. **Upload eligibility decision seam** (highest value, lowest drift risk):
   - Introduce `UploadEligibilityService` returning `UploadEligibilityDecision` (`allowed + reason`).
   - Reuse in web create + web store + API store.
   - Keep policy/controller thin adapters around the same decision result.
2. **Moderation authorization decision seam**:
   - Introduce `ModerationEligibilityService`/decision for publish/reject/soft-delete permissions.
   - Policy remains thin (`return $decision->allowed`).
3. **Optional follow-up seam**:
   - Move soft-delete transition into a dedicated action to align with publish/reject action pattern.

## Proposed 3C.2 implementation target (smallest safe step)
- Implement **only UploadEligibility decision/service + reason codes**, then wire all upload entry points to it.
- Keep existing behavior as baseline (no announce/scrape/UI changes, no role model changes).
- Add telemetry using reason codes on deny, mirroring Slice 3B structure.
