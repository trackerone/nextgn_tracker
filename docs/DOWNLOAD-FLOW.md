# Torrent download flow

The download endpoints serve stored `.torrent` payloads after personalizing the announce URL for the requesting user.

## Storage layout

- New uploads are stored on the configured torrents disk under the configured upload directory, and the exact relative path is persisted on the `torrents.storage_path` column.
- `Torrent::torrentStoragePath()` is the authoritative accessor. It uses `storage_path` for current rows and retains a hash-based fallback for older rows.
- `Torrent::torrentFilePath()` and `Torrent::hasTorrentFile()` resolve/check files through the configured disk.

## Endpoints

- Web: `GET /torrents/{torrent}/download` (`auth`, torrent download throttle).
- API: `GET /api/torrents/{torrent}/download` (`api`, `auth`, torrent download throttle).
- Magnet links are exposed by `GET /torrents/{torrent}/magnet`.

## Behavior

1. Download authorization uses torrent policies/eligibility so regular users can download only approved, non-banned torrents.
2. `TorrentDownloadService` loads the stored payload from the configured disk and decodes it with `BencodeService`.
3. The top-level `announce` value is overwritten with `config('tracker.announce_url')` plus the authenticated user's passkey. If the config contains `%s`, it is formatted with the passkey; otherwise the passkey is appended as a path segment.
4. Any `announce-list` is removed before re-encoding.
5. The response uses `application/x-bittorrent`, and grab/snatch history is updated through user-torrent stats services.

## Configuration

Set `TRACKER_ANNOUNCE_URL` to the public tracker announce base, usually with a `%s` placeholder, for example `https://tracker.example.com/announce/%s`.
