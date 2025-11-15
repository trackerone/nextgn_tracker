# Passkeys, announce flow & snatchlists

## Passkeys
- Each user record has a `passkey` column (see `database/migrations/*add_passkey_to_users_table.php`).
- `App\Models\User::ensurePasskey()` uses the tracker passkey service to ensure a unique 64-character hex value is assigned.
- Announce URLs come from `config('tracker.announce_url')`. If the value contains a `%s` placeholder the passkey is injected with `sprintf`; otherwise the passkey is appended as `/passkey`. Users can fetch it via `$user->announce_url`.
- Use `php artisan tracker:generate-passkeys` to assign passkeys to all existing users without one.

## User torrent stats
- The `user_torrents` table stores per-user stats for each torrent: uploaded, downloaded, completion timestamp, last announce time, plus `first_grab_at`/`last_grab_at` for download history.
- `App\Services\UserTorrentService` keeps these rows in sync whenever an announce request is processed.
- Rows are upserted using the announce payload; `completed_at` is filled only once when the `completed` event arrives.

## Snatchlist
- Logged-in users can view `/account/snatches` to see completed torrents.
- The page lists torrent name, size, uploaded/downloaded totals, and completion timestamps.
- Data source: `User::userTorrents()` relationship filtered by `completed_at`.

## Scrape endpoint
- `GET /scrape` returns aggregate stats for one or more torrents in a single bencoded response.
- Provide one or multiple `info_hash` query parameters (20-byte values, URL-encoded). At least one valid info hash is required.
- Response structure: `files` dictionary keyed by uppercase hex info hashes, each containing `complete` (seeders), `incomplete` (leechers), and `downloaded` (completed count).
- Use scrape for read-only stats; announce remains responsible for peer lifecycle and stat updates.
