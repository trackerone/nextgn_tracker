# RSS feeds

NextGN RSS feeds expose a small, metadata-aware torrent feed for users who want to subscribe from torrent clients or automation tools.

This is an RSS/autodl foundation only. It is not a rules engine and does not integrate with external autodl clients.

## Token model

Each user can have one nullable `rss_token` on their account. The RSS token is:

- generated with cryptographically safe random bytes through Laravel's `Str::random()` helper;
- unique when present;
- separate from the tracker passkey;
- rotated independently from API keys and passkeys.

Users manage the token at `/account/rss`. Rotating the token invalidates old RSS subscriptions immediately.

## Feed endpoint

```text
GET /rss/{token}
```

The token identifies the subscribing user. Invalid tokens return a not-found response and never fall back to passkeys or API keys.

Default feed size is 50 items. The `limit` query parameter is capped at 100 items.

## Filters

The first RSS slice supports these filters:

| Filter | Description | Example |
| --- | --- | --- |
| `q` | Case-insensitive search over torrent name and normalized metadata title/group | `?q=matrix` |
| `type` | Torrent metadata type | `?type=movie` |
| `resolution` | Torrent metadata resolution | `?resolution=2160p` |
| `source` | Torrent metadata source | `?source=bluray` |
| `release_group` | Torrent metadata release group | `?release_group=NTB` |
| `freeleech` | Boolean freeleech filter | `?freeleech=1` |
| `category` | Numeric category id | `?category=2` |
| `limit` | Number of RSS items, capped at 100 | `?limit=25` |

## Examples

```text
/rss/{token}
/rss/{token}?type=movie&resolution=2160p
/rss/{token}?source=bluray&freeleech=1
/rss/{token}?q=matrix&release_group=groupname
```

## Security boundaries

RSS feeds must remain user-scoped:

- RSS tokens are not tracker passkeys and are not API keys.
- Invalid tokens are rejected.
- Feeds only include visible torrents that the resolved user is eligible to download.
- Torrent metadata shown in RSS items is normalized through `TorrentMetadataView`.
- RSS currently links to the authenticated details page only. It intentionally does not add an RSS enclosure until a safe token-scoped download route exists.

## Output

The endpoint returns RSS 2.0 XML with newest eligible torrents first. Items include a title, details link, GUID, publish date, normalized metadata summary, and type category when available.
