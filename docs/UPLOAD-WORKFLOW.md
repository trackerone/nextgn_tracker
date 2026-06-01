# Upload workflow

## Entry points

- Web form: `GET /torrents/upload`, `POST /torrents` (`auth`, torrent upload throttle).
- API submission: `POST /api/uploads` (`api`, `auth`, torrent upload throttle).
- Both paths use `StoreTorrentRequest`/`UploadSubmissionRequest` validation and then call `SubmitTorrentUploadAction`.

## Accepted fields

Uploads require `name`, `type`, and `torrent_file`. Optional fields include `category_id`, `description`, `tags`/`tags_input`, `title`, `year`, `release_group`, `source`, `resolution`, `codecs`, `imdb_id`, `tmdb_id`, `language`, `audio_language`, `subtitle_language`, `subtitles`, and either `nfo_file` or `nfo_text` (not both). `.torrent` uploads must match the configured BitTorrent MIME and extension rules; NFO uploads must be plain text `.nfo`/`.txt`.

## Ingest and metadata flow

1. `SubmitTorrentUploadAction` builds a preflight context from the uploaded torrent payload and optional NFO data.
2. `UploadEligibilityService` evaluates duplicate torrents, missing metadata, and user eligibility. Duplicates redirect/return a conflict instead of creating a second torrent.
3. `TorrentIngestService` decodes the torrent, computes the SHA1 info hash from the bencoded `info` dictionary, extracts size/file counts, sanitizes persisted fields, and stores the raw payload on the configured torrents disk under the configured upload directory.
4. New torrents are created as `pending`, `is_approved=false`, `published_at=null`.
5. Canonical release metadata is persisted to `torrent_metadata`, including uploaded title/year/group/IMDb/TMDB/language/subtitle fields; NFO content is stored through `NfoStorageService`.
6. Upload success, duplicate, rejection, validation failure, and cleanup paths write audit/security telemetry without changing application behavior.

## Metadata conventions

- Language and subtitle fields are uploader-supplied metadata; this slice does not call external TMDB, IMDb, or other metadata providers.
- Use short Nordic/English codes such as `da`, `no`, `nb`, `nn`, `sv`, `fi`, and `en`.
- `subtitles` may contain multiple comma-separated values, for example `da,no,sv`.
- RSS feeds, saved RSS presets, and notification watch presets match language/subtitle filters against the uploaded `torrent_metadata` values through `TorrentMetadataView`.

## Moderation flow

- Staff moderation routes are under `/staff/torrents` and `/moderation/uploads`; API moderation routes are under `/api/moderation/uploads`.
- Approval uses `PublishTorrentAction` and transitions only `pending -> published`, setting approval/publication fields.
- Rejection uses `RejectTorrentAction` and transitions only `pending -> rejected`.
- Soft delete is staff-only and marks torrents as `soft_deleted`.
- Only published, approved, non-banned torrents are visible/downloadable to regular users; staff can inspect moderation states.

## Storage notes

- Torrent payloads use the `upload.torrents.disk` disk and `upload.torrents.directory` directory.
- The persisted `storage_path` is authoritative for current rows; `Torrent::torrentStoragePath()` retains a hash-based fallback for old rows.
- NFO payloads use the configured NFO disk/directory and are subject to size/character limits.
