# Torrent download flow

The download endpoint serves the pristine *.torrent* files stored under `storage/app/torrents/{YYYY}/{MM}/{UUID}.torrent` (the exact path is persisted per record) and injects the requesting user's passkey into the `announce` URL before responding.

## Storage layout

* Every upload is persisted through `TorrentIngestService`, which writes the raw payload to the configured torrents disk using a randomized directory layout (`torrents/{year}/{month}/{uuid}.torrent`). The resulting path is saved on each `Torrent` row (`storage_path`). Legacy rows without this value fall back to the old hash-based path.
* The `Torrent` model exposes helpers:
  * `torrentStoragePath()` – returns the stored relative path.
  * `torrentFilePath()` / `hasTorrentFile()` – resolve the absolute file and check its presence using the configured disk.

## Endpoint

* Route: `GET /torrents/{torrent:slug}/download` (`torrents.download`).
* Middleware: `auth`, `verified`.
* Controller: `TorrentDownloadController`.
* Behaviour:
  1. Only approved and non-banned torrents are downloadable.
  2. The `.torrent` file is loaded via `TorrentDownloadService` and decoded with `BencodeService`.
  3. The `announce` value is overwritten with the authenticated user's announce URL (`config('tracker.announce_url')` + passkey). Any multi-tracker lists are removed for now.
  4. The modified payload is re-encoded and streamed back with `Content-Type: application/x-bittorrent`.
  5. `UserTorrentService::recordGrab()` stores `first_grab_at` / `last_grab_at` timestamps in `user_torrents`.

## Tracker configuration

* `.env` exposes `TRACKER_ANNOUNCE_URL` (supports `%s` placeholder for the passkey). Example: `https://tracker.example.com/announce/%s`.
* `User::announce_url` and `TorrentDownloadService` both respect this format, falling back to appending the passkey if no placeholder exists.

## UI

* `resources/views/torrents/show.blade.php` displays a "Download .torrent" button pointing at `torrents.download` and a note explaining that the personal passkey is embedded automatically.
