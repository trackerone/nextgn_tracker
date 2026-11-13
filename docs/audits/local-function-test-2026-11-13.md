# Local function test — 13 November 2026

Scope: authenticated pages exercised on the HP Debian test server after PR #568. This is a function/UX correction pass, not production or load-test signoff.

| Finding | Correction in this change |
| --- | --- |
| `/torrents/upload` resolves to torrent details and returns 404 | Register the static upload route before the dynamic torrent route; regression test checks the matched controller. |
| CP437 NFO causes malformed UTF-8 JSON error | Normalize NFO text before parsing/storage; omit raw NFO from eligibility telemetry. UTF-8, BOM-marked UTF-16 and Windows-1252 are supported, with a heuristic for DOS artwork. |
| Metadata requires manual entry before backend parsing | Torrent and optional NFO inputs come first; an authenticated, CSRF-protected analysis endpoint previews detected fields without persisting a torrent. |
| Blank fields erase extracted metadata | Only nonempty supplied metadata overrides detected values. Explicitly reviewed source/resolution corrections are retained. |
| Too many fields displayed at once | Secondary metadata and readiness guidance are collapsible. Required fields are explained. Analysis preserves edits and clears stale automatic values when files change. |
| Two-film NFO can attach the first IMDb identity to the whole collection | Warn about multiple distinct IDs and leave single-film identity/year unset. This does not introduce a new collection data model. |
| Six files shown for three content files | Exclude padding entries from display count/size while preserving original torrent bytes and hash. An explicit maintenance command repairs previously stored statistics. |
| Pending torrent labelled approved | Show the actual moderation status; only published torrents are labelled approved. |
| QR code disappears | Render the server-generated QR SVG as an image data URL with explicit dimensions, preserving response sanitization and CSP. |
| Narrow content and horizontally scrolling navigation/results | Widen the authenticated layout, wrap navigation links, narrow the filter column and adapt result tables into cards on narrower screens. |
| Browse lacks decision-relevant metadata/download action | Show category, year, source, codecs and known language/subtitle information alongside existing size/swarm/date fields; add direct download links. Metadata still resolves through `TorrentMetadataView`. |
| Starter `Laravel` branding and missing initial categories | Correct starter app naming and document role/category seeding in installation instructions. |
| Dashboard repeats onboarding instructions | Collapse the quick-start guide so activity appears sooner. |

## Validation and remaining checks

Regression coverage includes route matching, analysis without persistence, NFO encoding, empty form values, reviewed overrides, collection warnings, padding repair and unchanged stored torrent payloads. Existing language-visibility expectations are updated to match the requested Browse behavior.

Local JavaScript syntax and a DOM-stub exercise cover automatic filling, preservation of edits, and replacement of stale detected values. PHP dependencies are not installed in this workspace; PHP tests, Pint, PHPStan and the asset build must be verified by GitHub Actions.

After merge, visually retest on the HP at 100% zoom: navigation, upload analysis using the original NFO, QR setup with a separate test administrator, pending/published labels, Browse metadata and filtering. Existing uploads need `torrents:refresh-file-stats --apply` to update old padding-inclusive statistics. Existing metadata is not silently rebuilt.

The earlier Discovery teaser warning, actual torrent download/announce/seeding, ordinary-member access, account administration and remaining navigation pages are not yet verified by the manual function test. NFO parsing is best effort; ambiguous legacy encodings and metadata absent from the files still require user review.
