# Upload and moderation flow map

This map records the current upload and moderation behavior. It is documentation only; keep it in sync with `routes/web.php`, `routes/api.php`, torrent policies, and the upload actions.

## Upload flow

### Web

1. `GET /torrents/upload` calls `TorrentUploadController@create` and authorizes `create` on `Torrent`.
2. `POST /torrents` uses `StoreTorrentRequest` validation and the torrent upload throttle.
3. `TorrentUploadController@store` calls `SubmitTorrentUploadAction` with the authenticated user, `.torrent` file, validated fields, and optional NFO file.
4. Duplicate uploads redirect to the existing torrent; denied/missing metadata paths return validation/authorization responses; successful uploads redirect to the torrent page with an awaiting-approval flash message.

### API

1. `POST /api/uploads` uses `UploadSubmissionRequest` and the same `SubmitTorrentUploadAction` path.
2. Duplicate uploads return `409` with duplicate metadata; successful uploads return `201` with `UploadSubmissionResource`; denied/missing metadata paths mirror the web flow as JSON/validation responses.

## Shared upload services

- `UploadEligibilityService` evaluates duplicate, metadata, and user eligibility decisions from a preflight context.
- `TorrentIngestService` decodes the torrent payload, computes the info hash, extracts size/file counts, sanitizes fields, stores the payload, and creates a pending torrent.
- `PersistTorrentMetadataService` writes canonical metadata to `torrent_metadata`.
- `NfoStorageService` stores NFO content when supplied.
- Upload success, duplicate, rejected, validation-failed, and cleanup paths write audit/security telemetry.

## Moderation flow

### Web

- Staff routes live under `/staff/torrents` and `/moderation/uploads` with auth, staff, and moderation throttling.
- Listing authorizes moderation access and shows pending/moderated uploads.
- Approval uses `PublishTorrentAction`; rejection uses `RejectTorrentAction`; soft delete marks the torrent soft-deleted from the moderation controller.

### API

- API moderation routes live under `/api/moderation/uploads` with `api`, `auth`, `staff`, and moderation throttling.
- Approval and rejection use the same lifecycle actions as the web flow and return `TorrentStatusResource` responses.

## State and visibility

- Torrent states are `pending`, `published`, `rejected`, and `soft_deleted`.
- Ingest always creates `pending`, `is_approved=false`, and `published_at=null`.
- Public browse/details/download require a published, approved, non-banned torrent.
- Staff can inspect moderation states through protected moderation surfaces.
