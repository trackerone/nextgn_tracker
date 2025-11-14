# Tracker engine (v1)

## Data model
- `torrents` keeps per-torrent stats (`seeders`, `leechers`, `completed`) and metadata such as `info_hash`, `size`, and visibility flags.
- `peers` links authenticated users to torrents with `peer_id`, `ip`, `port`, transfer stats, `is_seeder`, and `last_announce_at`. It enforces `unique(torrent_id, peer_id)` plus `torrent_id/is_seeder` indexes for fast counts.
- Factories: `database/factories/TorrentFactory.php` and `database/factories/PeerFactory.php` supply realistic fake data.

## Repository layer
- `App\Contracts\TorrentRepositoryInterface` defines lookups by slug/info-hash plus helpers for pagination, creation, stat increments, and peer-stat refreshes.
- `App\Repositories\EloquentTorrentRepository` implements the contract and powers HTTP + tracker flows.

## HTTP endpoints
- `GET /torrents` + `GET /torrents/{slug}` (auth + verified) list and show torrents with up-to-date stats.
- `GET /announce` (auth + verified + throttle) receives BitTorrent announce parameters, upserts peer rows, refreshes torrent stats, and answers with a bencoded dictionary containing `interval`, `complete`, `incomplete`, and a pruned peer list.

## Announce flow
1. Required params: `info_hash`, `peer_id`, `port`, `uploaded`, `downloaded`, `left`; optional `event`, `numwant`, and `ip`.
2. The tracker resolves the torrent by info-hash, authenticates the user, and upserts/deletes the peer:
   - `event=stopped` removes the peer row.
   - `event=completed` increments the torrent's `completed` counter.
   - Seeder status is derived from `left === 0`.
3. Peer stats feed `TorrentRepositoryInterface::refreshPeerStats`, ensuring `/torrents` and `/announce` reflect current `seeders`/`leechers`.
4. Responses are built via `App\Services\BencodeService` and always include:
   - `interval` (seconds between announces, currently 1800)
   - `complete` (seeders), `incomplete` (leechers)
   - `peers` → up to `numwant` most recent peers excluding the caller, filtered by a 60-minute freshness window.
