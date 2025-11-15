# Upload workflow

This document outlines the v1 torrent upload pipeline.

## Categories

- Categories live in the `categories` table (`App\Models\Category`).
- Torrents (`App\Models\Torrent`) belong to an optional category via `category_id`.
- Seed basic categories via `php artisan db:seed --class=CategorySeeder` or the default `DatabaseSeeder`.

## Upload form

- Authenticated + verified users can visit `/torrents/upload`.
- The form accepts:
  - `name`: required release title (sanitized before persist).
  - `type`: required enum (`movie`, `tv`, `music`, `game`, `software`, `other`).
  - `torrent_file`: required `.torrent` file (`application/x-bittorrent`).
  - `category_id`: optional, must exist in `categories`.
  - `description`, `source`, `resolution`, `tags`, `codecs`, and optional NFO file/text.
- Successful uploads redirect to the torrent show page with a flash banner (“awaiting approval”).
- Duplicate info hashes redirect to the original torrent instead of creating a new record.

## TorrentIngestService

`App\Services\Torrents\TorrentIngestService` parses uploaded files and persists torrents:

1. Decode the `.torrent` payload using `BencodeService::decode()`.
2. Compute the SHA1 info hash from the bencoded `info` dictionary (stored uppercase to match existing rows).
3. Extract:
   - Name (`info['name']`).
   - Size (`info['length']` or sum of multi-file entries).
   - Files count (1 or the multi-file array length).
4. Store the original `.torrent` file on the dedicated `torrents` disk under `torrents/{info_hash}.torrent`.
5. Create the torrent with metadata (type, codecs, tags, sanitized description, category, filenames, timestamps, parsed IMDb/TMDB IDs, etc.).
6. New uploads default to `is_approved = false` until staff moderation happens.

## Staff moderation

- Staff routes live under `/admin/torrents` (requires `role.min:8`).
- Filters: pending, approved, banned.
- List view shows uploader, category, filename, and timestamps.
- Inline form toggles approval, bans, ban reasons, and freeleech state.
- Updates redirect back to the filtered list with a success flash.

## Storage notes

- Torrent binaries live on the `torrents` disk (configurable via `config/filesystems.php`).
- Filenames are normalized to `{INFO_HASH}.torrent` for deterministic retrieval.

## Testing

- Unit tests cover `TorrentIngestService` parsing, sanitization, and duplicate handling.
- Feature tests exercise the upload form, store endpoint, and admin moderation routes.
