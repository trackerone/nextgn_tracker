# RSS feeds

NextGN RSS feeds expose a small, metadata-aware torrent feed for users who want to subscribe from torrent clients or automation tools.

This is an RSS/autodl foundation only. It is not a rules engine and does not integrate with external autodl clients.

## Token model

Each user can have one nullable `rss_token` on their account. The RSS token is:

- generated with cryptographically safe random bytes through Laravel's `Str::random()` helper;
- unique when present;
- separate from the tracker passkey;
- rotated independently from API keys and passkeys.

Users manage the token at `/account/rss`. Rotating the token invalidates old RSS subscriptions and RSS torrent download URLs immediately.

## Feed endpoint

```text
GET /rss/{token}
```

The token identifies the subscribing user. Invalid tokens return a not-found response and never fall back to passkeys or API keys.

Default feed size is 50 items. The `limit` query parameter is capped at 100 items.

## RSS torrent download links

Eligible RSS items include a token-scoped torrent enclosure for autodl and torrent clients:

```text
GET /rss/{token}/download/{torrent}
```

The `{token}` value is the user RSS token, and `{torrent}` is the numeric torrent id. The route resolves the user from `rss_token`, re-checks torrent visibility and download eligibility, then serves the same personalized `.torrent` payload path used by normal downloads. The URL does not expose storage paths, direct file paths, or the tracker passkey.

Example enclosure URL shape:

```text
https://tracker.example/rss/0123456789abcdef/download/12345
```

Rotating the RSS token invalidates both the feed URL and previously emitted enclosure/download URLs because old tokens no longer resolve to a user.

## Filters

The first RSS slice supports these filters:

| Filter | Description | Example |
| --- | --- | --- |
| `q` | Case-insensitive search over torrent name and normalized metadata title/group | `?q=matrix` |
| `type` | Torrent metadata type | `?type=movie` |
| `resolution` | Torrent metadata resolution | `?resolution=2160p` |
| `source` | Torrent metadata source | `?source=bluray` |
| `release_group` | Torrent metadata release group | `?release_group=NTB` |
| `language` | General torrent metadata language, matched case-insensitively with common Nordic/English names normalized | `?language=da` |
| `audio_language` | Torrent audio language metadata | `?audio_language=da` |
| `subtitle_language` | Primary subtitle language metadata | `?subtitle_language=da` |
| `subtitles` | Available subtitle languages; accepts comma-separated alternatives | `?subtitles=da,no,sv,fi` |
| `freeleech` | Boolean freeleech filter | `?freeleech=1` |
| `category` | Numeric category id | `?category=2` |
| `limit` | Number of RSS items, capped at 100 | `?limit=25` |

## Examples

```text
/rss/{token}
/rss/{token}?type=movie&resolution=2160p
/rss/{token}?source=bluray&freeleech=1
/rss/{token}?q=matrix&release_group=groupname
/rss/{token}?language=da
/rss/{token}?audio_language=da
/rss/{token}?subtitle_language=da
/rss/{token}?subtitles=da,no,sv,fi
/rss/{token}?type=movie&audio_language=da
/rss/{token}?type=movie&subtitle_language=da&resolution=2160p
```

Language filters accept ISO-like short values such as `da`, `no`, `nb`, `nn`, `sv`, `fi`, and `en`, plus common names such as `danish`, `dansk`, `norwegian`, `svensk`, `swedish`, `finnish`, and `english`. Matching is case-insensitive.

## Security boundaries

RSS feeds must remain user-scoped:

- RSS tokens are not tracker passkeys and are not API keys.
- Invalid tokens are rejected.
- Feeds only include visible torrents that the resolved user is eligible to download.
- RSS enclosure URLs use the RSS token route and do not include tracker passkeys.
- RSS downloads still enforce torrent visibility, approval, ban/deletion state, and ratio/freeleech/no-history eligibility before any `.torrent` payload is returned.
- Torrent metadata shown in RSS items is normalized through `TorrentMetadataView`.

## Output

The endpoint returns RSS 2.0 XML with newest eligible torrents first. Items include a title, details link, GUID, publish date, normalized metadata summary, type category when available, and an `application/x-bittorrent` enclosure URL for eligible downloads.
