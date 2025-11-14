# Torrent visibility, ratios & announce enforcement

## Torrent flags
- `is_approved`: uploaders/mods must approve a torrent before it becomes visible. Regular users cannot announce if the torrent is unapproved. Staff can bypass this check.
- `is_banned` + `ban_reason`: banned torrents are never listable or announceable. Staff use the reason to document takedowns.
- `freeleech`: informational flag that can later be used to waive download accounting.

## Ratio calculation
User ratio helpers aggregate all rows in `user_torrents`:
- `totalUploaded()` / `totalDownloaded()` read the summed columns.
- `ratio()` returns `null` when nothing has been downloaded yet, otherwise `uploaded / downloaded` as a float.

## User classes
Classes are derived from the ratio (staff overrides everything, disabled users fall back to `Disabled`). Thresholds:

| Ratio | Class |
| --- | --- |
| < 0.40 | Leech |
| 0.40 – 0.79 | User |
| 0.80 – 1.49 | Power User |
| ≥ 1.50 | Elite |

## Announce guardrails
- Banned torrents always return `failure reason = "Torrent is banned"`.
- Unapproved torrents behave the same for non-staff. Staff can still test them.
- When `event=started` and `left > 0`, users with a ratio below **0.20** are blocked with `"Your ratio is too low to start new downloads"`. Seeders (`left = 0`) and staff are never blocked by this rule.
