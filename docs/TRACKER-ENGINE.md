# Tracker engine

## Data model

- `torrents` stores swarm-facing state: `info_hash`, `seeders`, `leechers`, `completed`, status flags, moderation visibility, and persisted torrent storage path.
- `peers` links users to torrents with `peer_id`, IP, port, transfer counters, seeder state, and `last_announce_at`.
- `user_torrents` stores per-user aggregate transfer and snatch/grab history.

## Endpoints

- `GET /announce/{passkey}` is the BitTorrent announce endpoint. It is throttled and runs the announce pipeline after passkey resolution.
- `GET /scrape/{passkey}` returns bencoded aggregate stats for one or more `info_hash` query values.
- Authenticated web/API torrent browse and detail routes read the same persisted swarm counters that announce updates.

## Announce behavior

1. The passkey must resolve to an active user.
2. Required announce parameters are `info_hash`, `peer_id`, `port`, `uploaded`, `downloaded`, and `left`; optional parameters include `event`, `numwant`, and `ip`.
3. `info_hash` and `peer_id` are normalized as 20-byte values; invalid values return bencoded failure payloads.
4. The torrent is resolved by info hash. Non-staff users may announce only approved, non-banned torrents.
5. Banned clients, low-ratio new downloads, suspicious deltas, duplicate rapid announces, and completion rollbacks are guarded/logged by tracker services.
6. `event=stopped` removes the peer row; `event=completed` records completion once; seeder state is derived from `left === 0`.
7. Responses are bencoded dictionaries with `interval`, `complete`, `incomplete`, and a recent peer list excluding the caller.

## Scrape behavior

Scrape accepts one or more `info_hash` query values as 20-byte raw/url-encoded values or 40-character hex strings. Unknown, inaccessible, or invisible torrents return zeroed stats for that hash rather than leaking restricted data.
