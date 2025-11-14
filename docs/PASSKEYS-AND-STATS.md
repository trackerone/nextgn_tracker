# Passkeys, announce flow & snatchlists

## Passkeys
- Each user record has a `passkey` column (see `database/migrations/*add_passkey_to_users_table.php`).
- `App\Models\User::ensurePasskey()` generates a 32-character hex value if it is missing.
- Announce URLs follow the pattern `${config('tracker.announce_url')}/{passkey}`. Users can fetch it via `$user->announce_url`.
- Use `php artisan tracker:generate-passkeys` to assign passkeys to all existing users without one.

## User torrent stats
- The `user_torrents` table stores per-user stats for each torrent: uploaded, downloaded, completion timestamp and last announce time.
- `App\Services\UserTorrentService` keeps these rows in sync whenever an announce request is processed.
- Rows are upserted using the announce payload; `completed_at` is filled only once when the `completed` event arrives.

## Snatchlist
- Logged-in users can view `/account/snatches` to see completed torrents.
- The page lists torrent name, size, uploaded/downloaded totals, and completion timestamps.
- Data source: `User::userTorrents()` relationship filtered by `completed_at`.
