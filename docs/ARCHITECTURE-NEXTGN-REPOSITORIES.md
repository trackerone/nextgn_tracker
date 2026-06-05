# Repository layer

NextGN uses small repository interfaces where they already exist, primarily to keep controller and tracker flows testable without spreading query details through HTTP classes.

## Contracts and implementations

Interfaces live in `App\Contracts` and Eloquent implementations live in `App\Repositories`:

- Users, roles, topics, posts, conversations, and messages.
- Torrents via `TorrentRepositoryInterface` / `EloquentTorrentRepository`.

Bindings are registered in `App\Providers\RepositoryServiceProvider`. New code should follow existing repository seams when one already covers the aggregate being queried; do not introduce a parallel data-access style for the same concern.

## Torrent repository responsibilities

`TorrentRepositoryInterface` covers torrent lookup/pagination helpers used by web/API browse, upload duplicate handling, scrape, and announce flows. Tracker code resolves torrents by `info_hash` through this seam and refreshes peer counters after announce updates.

## Current routing note

Tracker protocol endpoints are passkey routes (`/announce/{passkey}` and `/scrape/{passkey}`), not session-authenticated browser routes. Web/API application routes remain protected by their route middleware and policies.
