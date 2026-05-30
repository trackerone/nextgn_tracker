# Notification Watch Presets

Notification watch presets let authenticated users save lightweight torrent filters and receive an internal NextGN notification when a newly approved and visible torrent matches.

This feature is intentionally small:

- It is not an autodl or rules engine.
- It does not run matching on announce or scrape requests.
- It does not send email, Discord, browser push, or other external notifications.
- It links to torrent details; users still choose whether to download.

## Supported filters

Watch presets reuse the safe RSS filter normalization and matching semantics where they are meaningful for notification matching:

- `q`
- `type`
- `resolution`
- `source`
- `release_group`
- `freeleech`
- `category`
- `language`
- `audio_language`
- `subtitle_language`
- `subtitles`

The RSS `limit` filter is ignored for notification watch presets because matching evaluates one newly published torrent at a time. Unsupported keys are discarded during request normalization and are not persisted.

## Relationship to RSS presets

RSS presets and notification watch presets share the same metadata-aware filter semantics through `RssFeedFilterNormalizer` and the RSS filter matcher. RSS presets remain public-token feed shortcuts, while notification watch presets are account-owned internal notification rules with no public identifier or URL.

## Trigger point

Matching runs from the torrent publication/approval action after a torrent transitions into the published and approved state. Editing an already approved torrent does not create a new trigger, and duplicate notifications for the same user, torrent, and preset are prevented at the database level.

## Security and eligibility boundaries

Before creating a notification, NextGN checks that:

- The torrent is approved, visible, and not banned or soft-deleted.
- The user is allowed to see/download the torrent through existing visibility eligibility logic.
- Ratio/download eligibility permits the user to download the torrent.
- The matching preset is enabled and owned by the user being notified.

Notifications never include tracker passkeys or RSS tokens, and notification links point to torrent details rather than direct download URLs.

## Internal notifications only

If a preset matches, NextGN stores a simple internal notification with a title like:

`New torrent matched your watch preset: {preset name}`

Users can view notifications at `/account/notifications`, mark individual notifications as read, or mark all notifications as read.
