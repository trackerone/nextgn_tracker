# Unified member notification inbox

Hermes audit finding 17: PM alerts and watch alerts existed in two stores, and
the old account notification screen displayed only watch matches.

## Product behavior

The existing **Account → Notifications** page is now one chronological inbox,
with combined pagination and explicit unread counts for:

- **Saved-search/watch alerts** stored in `torrent_watch_notifications`.
- **Private-message alerts** delivered through the Laravel database notification
  channel (`notifications.type = App\\Notifications\\NewPrivateMessageNotification`).

This is a unified **read experience**, not a destructive migration or a new
third event store. The SQL union is user-scoped and pages before fetching
details; it does not scan and merge every historical alert in PHP. Other
Laravel notification classes remain outside the inbox until their semantics
and access model are explicitly designed.

Each item has an independently scoped POST **Mark read** action. **Mark all
notifications as read** marks the current member's unread alerts in both
stores in one database transaction. Other members' alerts are unaffected.

**Read notification** means the member has acknowledged an alert, not that
the corresponding private message has been opened. Opening a PM conversation
marks the notifications corresponding to actually read messages as read.
The paginated HTML conversation view affects only the displayed messages;
the JSON conversation view marks all incoming messages read as before.

## Privacy and permissions

- No message body or stored message preview is copied into the account
  notification feed; the PM alert shows only that a message arrived and its
  sender name if available.
- The conversation link is shown only when the current member has
  `messages.access` permission **and** is a participant in that conversation.
  The PM endpoint retains its own authorization as the final gate.
- Torrent links are shown only for currently visible torrents. Existing
  torrent details/download authorization remains unchanged.
- Fetches, single-item actions and bulk read actions always scope to the
  authenticated user; unknown or another user's notification yields 404.
- Notification pages use private, no-store cache policy.
- No new navigation section or Sysop-wide notification channel setting is
  introduced. Owner-wide channel preferences and optional per-user delivery
  preferences require a separate, explicit product policy; they are not
  silently invented by this maintenance PR.

## Operations

No migrations or asset build are necessary. Existing notification rows and
read timestamps are preserved. The Laravel `notifications` and watch
notification tables must both be migrated already. Queued PM notifications
still require the configured queue worker if the site uses asynchronous
queue delivery.
