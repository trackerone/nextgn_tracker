# Recommendation Signals Foundation

Slice 68 adds an internal, read-only recommendation signal payload for future API or UI work. This is intentionally not a recommendation engine yet.

## Current boundary

- Signals are aggregated from existing torrent metadata only.
- Aggregation reuses `DiscoveryMetadataService`, so visible-torrent filtering, empty-value filtering, ordering, and aggregate limits stay aligned with discovery.
- The payload is not personalized.
- The payload does not use user history.
- The payload does not use download history.
- The payload does not use machine learning.
- Slice 69 exposes these signals through an authenticated read-only API endpoint.
- No public recommendation UI is introduced.
- Browse, RSS, and watch preset behavior are unchanged.

## Payload

`GET /api/recommendations/signals` returns the same versioned foundation payload as `App\Support\Recommendations\RecommendationSignalService::payload()`:

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

The all-time popular groups cover sources, resolutions, languages, and release groups. The trending group uses the existing safe discovery-style 30-day metadata window for sources, resolutions, and release groups.

## API endpoint

`GET /api/recommendations/signals` exposes the foundation payload for authenticated users. The endpoint is intentionally read-only and metadata-only:

- Authentication is required.
- The response reuses `RecommendationSignalService`, which delegates aggregate visibility filtering to the shared discovery metadata service.
- The endpoint does not return torrents or recommended torrents.
- The endpoint does not personalize by user.
- The endpoint does not use watch history or download history.
- The endpoint does not score, rank, or generate recommendations.
- Non-`GET` methods are not supported.
