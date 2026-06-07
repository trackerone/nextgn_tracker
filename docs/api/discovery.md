# Discovery API Contract

This document describes the current contract for the discovery endpoints so future slices keep the boundaries between metadata, trending, popular, and summary clear.

## Common rules

- Authentication is required for all discovery endpoints.
- The endpoints are read-only and must not mutate data.
- Metadata values that are `null` or empty are ignored.
- Hidden or unapproved torrents are excluded through visible filtering.
- Each aggregate group returns at most 25 entries.
- Ordering is by `count` descending, then `value` ascending for ties.
- Invalid `window` or `category` values return the standard Laravel validation response with HTTP `422`.
- Summary values count aggregate entries, not torrent rows.
- Output items use the same shape everywhere:

```json
{
  "value": "...",
  "count": 1
}
```

## GET `/api/discovery/metadata`

Returns all available metadata categories for all-time visible torrents.

### Categories returned

- `sources`
- `resolutions`
- `languages`
- `audio_languages`
- `subtitle_languages`
- `release_groups`

### Behavior

- No time window is applied.
- Only visible torrents are included.
- All available metadata categories are returned together.

## GET `/api/discovery/trending`

Returns time-window based discovery data from recent visible metadata only.

### Time window

- Default window: `30d`
- Supported windows:
  - `7d`
  - `30d`
  - `90d`

### Optional category

- `sources`
- `resolutions`
- `release_groups`

### Behavior

- The endpoint is based on a recent time window.
- Only visible metadata inside the selected window is included.
- The response contains only the requested category when a valid category is provided.
- Invalid `window` or `category` values are rejected with HTTP `422`.

## GET `/api/discovery/popular`

Returns all-time popular discovery data from all-time visible metadata only.

### Optional category

- `sources`
- `resolutions`
- `release_groups`

### Behavior

- No time window is applied.
- Only visible metadata is included.
- The response contains only the requested category when a valid category is provided.
- Invalid `category` values are rejected with HTTP `422`.

## GET `/api/discovery/summary`

Returns a compact overview of the discovery layer.

### Behavior

- Authentication is required.
- The response is read-only.
- `metadata` reflects the aggregate groups returned by `GET /api/discovery/metadata`.
- `popular` reflects the aggregate groups returned by `GET /api/discovery/popular`.
- `trending` reflects the default `30d` aggregate groups returned by `GET /api/discovery/trending`.
- Summary counts measure returned aggregate entries, not torrent rows.

### Response shape

```json
{
  "metadata": {
    "sources": 0,
    "resolutions": 0,
    "languages": 0,
    "audio_languages": 0,
    "subtitle_languages": 0,
    "release_groups": 0
  },
  "popular": {
    "sources": 0,
    "resolutions": 0,
    "release_groups": 0
  },
  "trending": {
    "window": "30d",
    "sources": 0,
    "resolutions": 0,
    "release_groups": 0
  }
}
```