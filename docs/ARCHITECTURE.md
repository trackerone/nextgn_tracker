# NextGN Tracker Architecture (3F metadata model)

## 1. System philosophy

NextGN treats torrent operations and release metadata as separate concerns:

- **Torrent operational state** belongs to `torrents` (visibility, moderation, swarm and access concerns).
- **Canonical release metadata** belongs to `torrent_metadata`.

This separation is intentional. It prevents API/web/moderation drift and gives one stable metadata contract independent of legacy torrent columns.

## 2. Authoritative metadata read seam

`App\Http\Resources\Support\TorrentMetadataView` is the **only allowed metadata read seam**.

All presentation layers must resolve metadata through this class:

- API resources (`TorrentBrowseResource`, `TorrentDetailsResource`, `ModerationTorrentResource`)
- Web controllers/views (`TorrentController`, `TorrentModerationController`)

No controller, resource, Blade view, or API transformer may hand-assemble metadata from mixed sources.

## 3. Metadata contract

`TorrentMetadataView::forTorrent()` returns a canonical metadata payload with this shape:

- `title`
- `year`
- `type`
- `resolution`
- `source`
- `release_group`
- `imdb_id`
- `tmdb_id`
- `nfo`

Contract rules:

1. If a related `torrent_metadata` row exists, use persisted metadata values.
2. If relation is loaded as `null` (row missing), use fallback values from legacy torrent columns where defined.
3. Null and empty values in persisted metadata are preserved (no silent replacement).

## 4. Fallback rules (strict)

When `torrent_metadata` is missing, fallback behavior is fixed:

- `type` ← `torrents.type`
- `resolution` ← `torrents.resolution`
- `source` ← `torrents.source`
- `imdb_id` ← `torrents.imdb_id`
- `tmdb_id` ← `torrents.tmdb_id`
- `nfo` ← `torrents.nfo_text`
- `title`, `year`, `release_group` remain `null` unless persisted metadata exists

Do not add ad-hoc fallback logic anywhere else.

## 5. Read flow

Required read path:

1. **Controller/Action** fetches torrent models with eager-loaded `metadata` relation.
2. **Resource/View adapter** calls `TorrentMetadataView::forTorrent()` (or `mapByTorrentId()` for collections).
3. Response/view consumes only this normalized metadata payload.

Canonical flow:

`Controller -> Resource/View -> TorrentMetadataView`

## 6. Eager-loading requirement

Any endpoint or web page that reads metadata **must eager-load** `metadata` in the query (`with('metadata')` or equivalent).

Reason:

- Prevent N+1 lookups.
- Ensure deterministic fallback behavior when relation is explicitly loaded as null.
- Keep API, web, and moderation behavior identical under load.

## 7. Strict anti-patterns (not allowed)

The following are architecture violations:

- Reading metadata fields directly from `Torrent` in controllers/resources/views when `TorrentMetadataView` should be used.
- Building metadata arrays manually per endpoint.
- Mixing persisted metadata and legacy columns inline in Blade/API resources.
- Lazy metadata access in loops without eager loading.
- Returning different metadata shapes across API/web/moderation for the same torrent.

## 8. Completed 3F slices in current implementation

The metadata architecture in this repository reflects completed slices **3F.2–3F.6**:

- **3F.2**: dedicated `torrent_metadata` persistence model/table and torrent relation wiring.
- **3F.3**: `TorrentMetadataView` introduced as canonical metadata read seam.
- **3F.4**: API read surfaces switched to `TorrentMetadataView` contract.
- **3F.5**: Web browse/detail and moderation surfaces switched to same contract.
- **3F.6**: Cross-surface consistency and fallback behavior enforced by feature/unit tests.

## 9. Engineering rule of record

For metadata reads, there is one rule:

> If code needs torrent metadata, it must come from `TorrentMetadataView`.

Any deviation should be treated as a regression and blocked in review.
