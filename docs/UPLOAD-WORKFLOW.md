# Upload workflow

This document outlines the v1 torrent upload pipeline.

## Categories

- Categories live in the `categories` table (`App\Models\Category`).
- Torrents (`App\Models\Torrent`) belong to an optional category via `category_id`.
- Seed basic categories via `php artisan db:seed --class=CategorySeeder` or the default `DatabaseSeeder`.

## Upload form

- Authenticated + verified users can visit `/torrents/upload`.
- The form accepts:
  - `torrent`: required `.torrent` file (`application/x-bittorrent`).
  - `category_id`: optional, must exist in `categories`.
  - `description`: optional text.
- Successful uploads redirect to the torrent show page with a flash banner (“awaiting approval”).
- Duplicate info hashes redirect to the original torrent instead of creating a new record.

## TorrentUploadService

`App\Services\TorrentUploadService` parses uploaded files and persists torrents:

1. Decode the `.torrent` payload using `BencodeService::decode()`.
2. Compute the SHA1 info hash from the bencoded `info` dictionary (stored uppercase to match existing rows).
3. Extract:
   - Name (`info['name']`).
   - Size (`info['length']` or sum of multi-file entries).
   - Files count (1 or the multi-file array length).
4. Store the original `.torrent` file on the local disk under `torrents/{info_hash}.torrent`.
5. Create the torrent with metadata (`description`, `category_id`, `original_filename`, `uploaded_at`).
6. New uploads default to `is_approved = false` until staff moderation happens.

## Staff moderation

- Staff routes live under `/admin/torrents` (requires `role.min:8`).
- Filters: pending, approved, banned.
- List view shows uploader, category, filename, and timestamps.
- Inline form toggles approval, bans, ban reasons, and freeleech state.
- Updates redirect back to the filtered list with a success flash.

## Storage notes

- Torrent binaries live on the default `local` disk (configurable via `filesystems.php`).
- Filenames are normalized to `{INFO_HASH}.torrent` for deterministic retrieval.

## Testing

- Unit tests cover `TorrentUploadService` parsing + duplicate handling.
- Feature tests exercise the upload form, store endpoint, and admin moderation routes.
