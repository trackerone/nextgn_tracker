# Watch preset notification lifecycle

The watch-presets feature uses `TorrentRssFilterMatcher` and live torrent download
eligibility. It is not an independent full-catalog search service.

## Publication

1. Moderation publishes and approves the torrent.
2. `PublishTorrentAction` dispatches `MatchTorrentWatchPresets` **after the
   publishing transaction commits**. Dispatch failures are logged but do not
   revert an otherwise successful approval.
3. The job reloads the torrent and its canonical `torrent_metadata` row,
   verifies visibility, then examines enabled watch presets in bounded chunks.
4. Each match rechecks member access, ratio eligibility, and current preset
   settings before creating the notification.
5. `torrent_watch_notifications` has a unique key per user, torrent and preset.
   Job retries and repeated metadata rematches do not create duplicate alerts.

## Canonical metadata changes

`PersistTorrentMetadataService` queues a rematch only for **already visible**
torrents whose actual canonical watch-filter metadata changed: title, year,
type, resolution, source, release group, language, audio language, subtitle
language or subtitles. An ordinary upload is still pending at persistence
time, so it does not send notifications until approval. Non-filter metadata
changes do not schedule a rematch.

The external metadata enrichment job currently writes
`torrent_external_metadata`, **not** the canonical `torrent_metadata` fields
used by `TorrentRssFilterMatcher`. Therefore an external TMDb lookup does not
by itself change watch matches. If a future enrichment path writes canonical
filter fields, it should do so through `PersistTorrentMetadataService` to
trigger a rematch. Do not match on external poster/overview/title without
explicitly changing the shared filter model.

## Deployment and operations

`MatchTorrentWatchPresets` implements `ShouldQueue`: on a configured
asynchronous queue, notification matching no longer blocks the approval
request. With Laravel's `sync` queue driver, jobs still execute inline;
configure and supervise a real queue worker if asynchronous moderation is
required. Monitor the worker in Sysop Operations.

There are no new settings, migrations or UI sections. If an asynchronous
worker is unavailable, pending watch jobs cannot deliver notifications until
the worker resumes. Revoking a preset or hiding/banning a torrent before the
job executes prevents newly generated alerts.

Security: the watcher never gains access by receiving a notification; all
regular torrent download and view endpoints must continue to enforce their
own authorization at request time.
