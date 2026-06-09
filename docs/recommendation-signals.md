# Recommendation Signals Foundation

Slice 68 adds an internal, read-only recommendation signal payload for future API or UI work. This is intentionally not a recommendation engine yet.

## Current boundary

- Signals are aggregated from existing torrent metadata only.
- Aggregation reuses `DiscoveryMetadataService`, so visible-torrent filtering, empty-value filtering, ordering, and aggregate limits stay aligned with discovery.
- The payload is not personalized.
- The payload does not use user history.
- The payload does not use download history.
- The payload does not use machine learning.
- No public recommendation UI or endpoint is introduced by this slice.
- Browse, RSS, and watch preset behavior are unchanged.

## Payload

`App\Support\Recommendations\RecommendationSignalService::payload()` returns a versioned foundation payload:

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
