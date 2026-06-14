# Discovery API Contract

This document describes the current contract for the discovery endpoints so future slices keep the boundaries between metadata, trending, popular, RSS suggestions, summary, and home clear.

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

## GET `/api/discovery/rss-suggestions`

Returns all-time discovery metadata suitable for future RSS preset creation UI.

### Optional category

- `sources`
- `resolutions`
- `languages`
- `release_groups`

### Behavior

- Authentication is required.
- The endpoint is read-only.
- No time window is applied.
- No personalization, recommendation logic, or RSS generation behavior is applied.
- The endpoint reuses `DiscoveryMetadataService`, including visible filtering, null and empty filtering, ordering, and the 25 entry aggregate limit.
- The response contains only the requested category when a valid category is provided.
- Invalid `category` values are rejected with HTTP `422`.

### Response shape

```json
{
  "sources": [],
  "resolutions": [],
  "languages": [],
  "release_groups": []
}
```

## GET `/api/discovery/watch-preset-suggestions`

Returns all-time discovery metadata suitable for future notification watch preset creation UI.

### Optional category

- `sources`
- `resolutions`
- `languages`
- `release_groups`

### Behavior

- Authentication is required.
- The endpoint is read-only.
- No time window is applied.
- No personalization, recommendation logic, watch preset saving behavior, or notification behavior is applied.
- The endpoint reuses `DiscoveryMetadataService`, including visible filtering, null and empty filtering, ordering, and the 25 entry aggregate limit.
- The response contains only the requested category when a valid category is provided.
- Invalid `category` values are rejected with HTTP `422`.

### Response shape

```json
{
  "sources": [],
  "resolutions": [],
  "languages": [],
  "release_groups": []
}
```

## GET `/api/recommendations/signals`

Returns the recommendation signal foundation payload for authenticated users. This endpoint exposes reusable metadata signals only; it does not return recommended torrents.

### Behavior

- Authentication is required.
- The endpoint is read-only.
- The endpoint reuses `RecommendationSignalService` and the shared discovery metadata aggregation path.
- Visibility filtering remains delegated to the shared discovery metadata service.
- No personalization, recommendation scoring, download history, watch history, or recommendation engine behavior is applied.
- The all-time `popular` group returns sources, resolutions, languages, and release groups.
- The `trending` group returns the existing 30-day sources, resolutions, and release groups metadata signals.

### Response shape

```json
{
  "version": 1,
  "engine": "metadata_signals_foundation",
  "personalized": false,
  "uses_user_history": false,
  "uses_download_history": false,
  "signals": {
    "popular": {
      "sources": [],
      "resolutions": [],
      "languages": [],
      "release_groups": []
    },
    "trending": {
      "window": "30d",
      "sources": [],
      "resolutions": [],
      "release_groups": []
    }
  }
}
```


## GET `/api/recommendations/engine`

Returns the recommendation engine foundation payload for authenticated users. This endpoint exposes the readonly, metadata/signal-based engine foundation only; it does not return recommended torrents or personalized output.

### Behavior

- Authentication is required.
- The endpoint is read-only.
- The endpoint reuses `RecommendationEngineService`, which consumes `RecommendationSignalService` output instead of reading torrent metadata directly.
- Visibility filtering remains delegated to the shared discovery metadata service through recommendation signals.
- No recommended torrents, torrent IDs, scores, ranks, personalization fields, user history, download history, or watch history are returned.
- Non-`GET` methods are not supported.

### Response shape

```json
{
  "version": 1,
  "engine": "metadata_recommendation_engine_foundation",
  "readonly": true,
  "uses_user_history": false,
  "uses_download_history": false,
  "uses_watch_history": false,
  "metadata_categories": [],
  "signal_groups": [],
  "weights": {},
  "signals": {
    "popular": {
      "sources": [],
      "resolutions": [],
      "languages": [],
      "release_groups": []
    },
    "trending": {
      "window": "30d",
      "sources": [],
      "resolutions": [],
      "release_groups": []
    }
  }
}
```


## GET `/api/recommendations/candidates`

Returns readonly recommendation candidate groups for authenticated users. This endpoint exposes system-wide metadata combinations generated from the recommendation engine foundation; it does not return torrents or personalized recommendations.

### Behavior

- Authentication is required.
- The endpoint is read-only.
- The endpoint reuses `RecommendationEngineService` candidate generation.
- Candidate groups are system-wide metadata combinations only.
- No recommended torrents, torrent IDs, scores, ranks, personalization fields, user history, download history, or watch history are returned.
- Non-`GET` methods are not supported.

### Response shape

```json
{
  "version": 1,
  "readonly": true,
  "candidate_groups": [
    {
      "source": "WEB-DL",
      "resolution": "1080p"
    }
  ]
}
```

## GET `/api/discovery/home`

Returns a compact discovery payload for the frontend landing section.

### Behavior

- Authentication is required.
- The endpoint is read-only.
- No query parameters are supported yet.
- The endpoint reuses `DiscoveryMetadataService`, so every aggregate list in `summary`, `popular`, and `trending` is capped at 25 entries.
- `summary.metadata` reflects the aggregate groups returned by `GET /api/discovery/metadata`.
- `summary.popular` reflects the aggregate groups returned by `GET /api/discovery/popular`.
- `summary.trending` reflects the default `30d` aggregate groups returned by `GET /api/discovery/trending`.
- `popular` matches `GET /api/discovery/popular`.
- `trending` matches `GET /api/discovery/trending` with the default `30d` window.
- Summary counts measure the capped aggregate entries, not torrent rows.

### Response shape

```json
{
  "summary": {
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
  },
  "trending": {
    "window": "30d",
    "sources": [],
    "resolutions": [],
    "release_groups": []
  },
  "popular": {
    "sources": [],
    "resolutions": [],
    "release_groups": []
  }
}
```

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