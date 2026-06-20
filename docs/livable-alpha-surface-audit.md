# Slice 101 – Livable Alpha Surface Audit & Launch Gap Map

Date: 2026-06-19
Repository: `trackerone/nextgn_tracker`
Scope: product/launch surface audit only. This slice intentionally does **not** implement new product features or continue the discovery-operations foundation track.

## Purpose

This document maps what is still missing before NextGN feels like a real, usable tracker site for ordinary members, uploaders, staff, and administrators. It is meant to guide the next launch-surface slices, not to become a theoretical architecture plan.

## Current strategic lock

- **Discovery scope is paused for now.** Slices 93 through 100 made discovery and discovery operations strong enough for Livable Alpha.
- **Product surface comes next:** browse, torrent detail, upload, account, moderation, navigation, responsive UX, seed data, and launch health.
- **No new foundation track is recommended here.** Follow-up work should make existing surfaces usable, clearer, and safer.
- **Metadata remains powerful but quiet.** Metadata should reduce user effort across browse, search, RSS, watch presets, notifications, and moderation without badge overload.
- **Strict priority language:** P0 is reserved for items that block reasonable use of the tracker by members, uploaders, staff, or admins.

## Executive summary

NextGN has a strong technical and metadata/discovery base, but the user-facing tracker product is still mid-alpha. The authenticated Blade shell, browse page, upload form, details page, RSS/watch tooling, discovery account surface, upload moderation queue, sysop operations dashboard, logs, and health endpoint provide real foundations. However, ordinary users can still run into missing account orientation, dense browse/detail screens, a basic upload workflow, fragmented settings, inconsistent admin pages, limited staff queues, weak demo data, and mobile/table-heavy friction.

Estimated launch posture after Slice 100:

- Technical foundation: **75–80%**
- Metadata/discovery foundation: **80–85%**
- Product as a usable tracker site: **50–60%**
- Livable Alpha launch readiness: **about 55%**
- Finished public tracker readiness: **35–45%**

## Launch readiness scorecard

| Area | Current state | Livable Alpha readiness | Main blocker | Priority |
| --- | --- | --- | --- | --- |
| Front page / discovery home | Authenticated dashboard plus discovery surface; `/` redirects to login. | Partial | No clear member orientation from landing/dashboard into tracker basics. | P1 Livable Alpha Important |
| Browse / torrent listing | Search, filters, grouped/flat rows, saved views, RSS query handoff. | Partial | Dense listing and limited first-class filters/mobile ergonomics. | P1 Livable Alpha Important |
| Torrent detail page | Download, magnet, follow, facts, metadata, stats, description, NFO. | Partial | Missing trust/recovery affordances such as file list, report action, client guidance, and richer media context. | P1 Livable Alpha Important |
| Upload flow | Basic upload form, moderation disclaimer, My Uploads status. | Partial | Uploaders lack preflight confidence, field-level guidance, edit/resubmit loop, and screenshot/media support. | P0 Launch Blocker |
| Account / user dashboard | Dashboard, ratio/snatches, notifications, RSS, invites, follows, watch center. | Partial | No coherent account/settings hub for passkey/client setup, profile, API keys, and preferences. | P0 Launch Blocker |
| RSS / watch presets / notifications | RSS tokens/presets, watch presets, notification list. | Partial | Concepts overlap and setup guidance is thin. | P1 Livable Alpha Important |
| Staff / moderation | Upload moderation queue and actions exist. | Partial | Upload-only queue; limited report/escalation/user/content moderation UX. | P1 Livable Alpha Important |
| Admin / operations | Sysop operations, logs, ratio/metadata/invite tools. | Partial | Admin surface is fragmented and some pages are internal-tool styled. | P1 Livable Alpha Important |
| Metadata and discovery visibility | Strong APIs and panels; metadata appears in browse/detail/upload. | Strong enough | Risk of internal visibility exceeding ordinary-user clarity. | P2 Polish / Later |
| Navigation | Shared authenticated nav exposes core tracker areas. | Partial | Long horizontal nav mixes product, account, community, and staff items. | P1 Livable Alpha Important |
| Mobile / responsive behavior | Tailwind responsive shell and overflow tables. | Weak | Table-heavy surfaces rely on horizontal scrolling instead of mobile-first summaries. | P1 Livable Alpha Important |
| Design / visual identity | Dark Blade shell is cohesive in core app. | Partial | Admin pages and some flows diverge; product identity is still generic. | P2 Polish / Later |
| Empty states | Many lists include empty states. | Partial | Empty states are inconsistent and not always action-oriented. | P2 Polish / Later |
| Error states | Laravel validation and error pages exist. | Partial | Route-specific recovery and client/setup failure guidance are thin. | P1 Livable Alpha Important |
| Seeder / demo data | Seeders exist, but alpha product data depth is unclear. | Weak | Insufficient realistic demo library and role journeys for launch review. | P0 Launch Blocker |
| Live readiness / health / monitoring | Health endpoint, operations dashboard, logs, production docs. | Partial | Launch checklist needs user-facing operational readiness, smoke paths, and demo/staging validation. | P1 Livable Alpha Important |

## Priority definitions

- **P0 Launch Blocker:** ordinary users, uploaders, staff, or admins cannot reasonably use the alpha tracker without this fixed or explicitly scoped out.
- **P1 Livable Alpha Important:** the alpha can run, but user/staff effort, support load, or confidence will be materially worse.
- **P2 Polish / Later:** valuable after core journeys are reliable.
- **Parked / Not now:** intentionally deferred to prevent feature bloat and discovery scope creep.

## Surface audit

### 1. Front page / discovery home

- **Current status:** `/` redirects to login, `/home` provides an authenticated dashboard, and `/my/discovery` mounts discovery/recommendation panels.
- **Good enough:** Authenticated users have a central dashboard with entry points to torrents, upload, discovery, messages/forum summaries, and account-related activity.
- **Weak:** Guest/member orientation is limited. A new invited user still needs a clearer “what do I do first?” path for browse, download, passkey/client setup, upload, RSS, and moderation expectations.
- **Launch blocker:** None if Livable Alpha remains invite-only and supervised.
- **Nice-to-have:** A small alpha landing/onboarding panel, rules/expectations summary, and “first five actions” checklist.
- **Recommended next slice:** **Slice 102 – Livable Alpha Browse and Dashboard Orientation Pass**.
- **Priority:** P1 Livable Alpha Important.

### 2. Browse / torrent listing

- **Current status:** Browse includes text search, core filters, metadata-aware saved views, RSS query handoff, grouped/flat listings, pagination, and metadata display.
- **Good enough:** Members can find torrents, apply common filters, save intent, and jump from browse to detail/download.
- **Weak:** The page is dense and table-oriented. Some metadata power is hidden behind query syntax or explanatory copy. Mobile usability is likely functional but not comfortable.
- **Launch blocker:** None if the alpha catalog is small and curated; becomes a blocker if users must browse a broad demo catalog without guidance.
- **Nice-to-have:** Result-count summary, clearer no-results recovery, compact mobile cards, and limited additional first-class filters only where they reduce effort.
- **Recommended next slice:** **Slice 102 – Browse Surface Hardening**.
- **Priority:** P1 Livable Alpha Important.

### 3. Torrent detail page

- **Current status:** Details expose download/magnet actions, follow action, quick facts, metadata, stats, links, description, and NFO.
- **Good enough:** A member can inspect a torrent and attempt download from one page.
- **Weak:** Detail lacks file list, screenshots/media preview, comments/report action, clear client setup help, uploader trust context, and stronger unavailable/download-denied recovery.
- **Launch blocker:** None for a controlled technical alpha; risk rises if ordinary users need self-serve troubleshooting.
- **Nice-to-have:** Copy buttons, file tree, report button, basic screenshot slot, and concise “how to use this torrent” help.
- **Recommended next slice:** **Slice 103 – Torrent Detail Page Hardening**.
- **Priority:** P1 Livable Alpha Important.

### 4. Upload flow

- **Current status:** Upload supports torrent file, category/type/source/resolution, tags, codecs, description, NFO, moderation submission, and My Uploads status.
- **Good enough:** A trusted uploader can submit a basic torrent into moderation.
- **Weak:** Uploaders do not get a parsed preflight preview, duplicate/upgrade guidance, screenshot/media support, field-level help everywhere, progress feedback, or an edit/resubmit loop after rejection.
- **Launch blocker:** **P0 Launch Blocker:** Livable Alpha needs a predictable uploader path. Without clearer validation, rejection recovery, and enough guidance, staff support load will be too high and uploaders may stall.
- **Nice-to-have:** Rich media attachments, automatic metadata enrichment previews, advanced duplicate comparison, and uploader scoring.
- **Recommended next slice:** **Slice 104 – Upload Flow Hardening**.
- **Priority:** P0 Launch Blocker.

### 5. Account / user dashboard

- **Current status:** The dashboard, snatches/ratio, notifications, RSS, watch presets, invites, follows, saved views, API-key route, and discovery surfaces exist in pieces.
- **Good enough:** Members can reach several personal tools and see tracker activity.
- **Weak:** There is no coherent account/settings hub that explains passkey, announce URL, RSS token, API keys, profile, notifications, security, and preferences in one place.
- **Launch blocker:** **P0 Launch Blocker:** ordinary members need self-serve account and client setup basics to reasonably use a private tracker without staff hand-holding.
- **Nice-to-have:** Profile personalization, privacy controls, contribution stats, achievement/reputation economy.
- **Recommended next slice:** **Slice 105 – Account and Tracker Setup Hub**.
- **Priority:** P0 Launch Blocker.

### 6. RSS / watch presets / notifications

- **Current status:** RSS token rotation, RSS presets, watch presets, watch center, notification list, and discovery suggestions exist.
- **Good enough:** The primitives are present and metadata-aware.
- **Weak:** RSS, watch presets, follows, saved views, and notifications overlap conceptually. Users need clearer setup examples, copy/test affordances, and one explanation of which tool to use when.
- **Launch blocker:** None if account setup hub explains the basics.
- **Nice-to-have:** Feed health diagnostics, delivery preferences, unread nav badge, and preset templates.
- **Recommended next slice:** **Slice 106 – RSS and Watch Center Clarity Pass**.
- **Priority:** P1 Livable Alpha Important.

### 7. Staff / moderation

- **Current status:** Staff can review pending uploads, approve, reject with reason, soft-delete, and review recently moderated items.
- **Good enough:** Upload moderation is usable for a small alpha.
- **Weak:** Moderation is upload-centric. There is no obvious report queue, user moderation hub, claim/assign workflow, escalation notes, or unified content review surface.
- **Launch blocker:** Not P0 if alpha staff manually coordinate out-of-band and launch scope is upload moderation first.
- **Nice-to-have:** Report flow, assignment, moderator notes, bulk actions, and structured duplicate comparison.
- **Recommended next slice:** **Slice 107 – Staff Moderation Surface Hardening**.
- **Priority:** P1 Livable Alpha Important.

### 8. Admin / operations

- **Current status:** Sysop operations, audit/security logs, admin invites, ratio settings, metadata settings, and role update routes exist.
- **Good enough:** Operators have meaningful health/log/settings surfaces for a technical alpha.
- **Weak:** Admin experiences are fragmented. Some admin pages use standalone styling rather than the shared app shell, and a general admin dashboard/user management surface is incomplete.
- **Launch blocker:** Not P0 if a small trusted team can operate through existing pages and CI/deploy runbooks.
- **Nice-to-have:** Unified admin dashboard, user search, support tooling, storage/mail/queue drilldowns, and common app chrome.
- **Recommended next slice:** **Slice 108 – Admin Surface Consolidation Map**.
- **Priority:** P1 Livable Alpha Important.

### 9. Metadata and discovery visibility for normal users

- **Current status:** Metadata powers browse, detail, upload, RSS, watch presets, saved views, discovery APIs, recommendations, and operations panels.
- **Good enough:** The metadata-first strategy is visible and strong enough for Livable Alpha.
- **Weak:** Some discovery/operations surfaces are more staff/internal than member-friendly. Normal users need benefits, not explanation density.
- **Launch blocker:** None. Discovery scope is explicitly paused.
- **Nice-to-have:** Better wording, fewer badges, clearer metadata hierarchy, and context-specific labels.
- **Recommended next slice:** Handle inside browse/detail/account hardening rather than creating a new discovery slice.
- **Priority:** P2 Polish / Later.

### 10. Navigation

- **Current status:** The shared authenticated nav links to dashboard, torrents, upload, discovery, watch center, follows, uploads, forum, PM, ratio, moderation, and operations by role.
- **Good enough:** Core routes are discoverable from a single bar.
- **Weak:** The nav is long, horizontally scrolling, and mixes primary tracker tasks with account/community/staff links. Some account surfaces are hidden or indirectly exposed.
- **Launch blocker:** None, but it will increase confusion in unsupervised alpha.
- **Nice-to-have:** Grouped account menu, role-aware staff/admin menu, mobile drawer, and clearer labels.
- **Recommended next slice:** **Slice 109 – Navigation and Mobile IA Pass**.
- **Priority:** P1 Livable Alpha Important.

### 11. Mobile / responsive behavior

- **Current status:** The app uses responsive Tailwind classes and overflow containers for wide tables.
- **Good enough:** Pages should not catastrophically break on narrow screens.
- **Weak:** Overflow scrolling is not the same as a good mobile tracker experience. Browse, moderation, logs, and account tables need compact summaries or cards.
- **Launch blocker:** Not P0 if alpha target is desktop-first, but should be stated explicitly.
- **Nice-to-have:** Mobile browse cards, sticky actions, collapsed filters, and better touch targets.
- **Recommended next slice:** **Slice 110 – Mobile Alpha Pass**.
- **Priority:** P1 Livable Alpha Important.

### 12. Design / visual identity

- **Current status:** The core app uses a dark Tailwind/Blade visual system with brand accents; some admin pages diverge.
- **Good enough:** The product has a coherent enough base for alpha users.
- **Weak:** Visual identity is still generic, and inconsistent admin/internal styling weakens trust.
- **Launch blocker:** None unless visual inconsistency hides critical actions.
- **Nice-to-have:** Design tokens, shared admin layout, clearer typography hierarchy, and restrained metadata badges.
- **Recommended next slice:** Fold into page hardening; avoid cosmetic redesign without UX blocker value.
- **Priority:** P2 Polish / Later.

### 13. Empty states

- **Current status:** Empty states exist across dashboard lists, browse, My Uploads, discovery/follows, RSS/watch, notifications, snatches, and moderation.
- **Good enough:** Users are usually not left with completely blank tables.
- **Weak:** Empty states are inconsistent and often do not guide the next action strongly enough.
- **Launch blocker:** None.
- **Nice-to-have:** Standard empty-state pattern with primary action, explanation, and recovery suggestions.
- **Recommended next slice:** **Slice 111 – Empty State and No-Results Pass**.
- **Priority:** P2 Polish / Later.

### 14. Error states

- **Current status:** Global validation rendering and 404/500/503 pages exist; forms use Laravel validation.
- **Good enough:** Basic errors are displayed and catastrophic errors have branded pages.
- **Weak:** User journeys need contextual recovery: download denied, missing passkey/client setup, upload validation, no results, expired RSS token, unauthorized moderation/admin access.
- **Launch blocker:** Not broadly P0, but specific account/download setup errors may block first-use success.
- **Nice-to-have:** Copy buttons, support links, retry guidance, and focused field-level validation.
- **Recommended next slice:** **Slice 112 – Error and Recovery State Pass**.
- **Priority:** P1 Livable Alpha Important.

### 15. Seeder / demo data

- **Current status:** The repository contains tests and seed-oriented docs, but the launch demo dataset does not appear product-complete enough to validate ordinary member, uploader, staff, and admin journeys.
- **Good enough:** Existing factories/tests likely cover core mechanics.
- **Weak:** A Livable Alpha needs realistic torrent catalogs, metadata diversity, users/roles, pending/rejected uploads, RSS/watch examples, notifications, snatches, and operational states.
- **Launch blocker:** **P0 Launch Blocker:** reviewers cannot confidently validate launch readiness without representative data and repeatable demo roles.
- **Nice-to-have:** Larger themed catalogs, screenshots/media samples, historical activity, and optional synthetic load scenarios.
- **Recommended next slice:** **Slice 113 – Livable Alpha Seeder and Demo Roles**.
- **Priority:** P0 Launch Blocker.

### 16. Live readiness / health / monitoring

- **Current status:** `/health`, production operations docs, sysop operations dashboard, audit/security logs, runtime job controls, and production readiness tests exist.
- **Good enough:** The operational foundation is credible for an internal alpha.
- **Weak:** Launch readiness needs a concise checklist that ties infrastructure health to product journeys: signup/invite, browse, detail, download, upload, moderation, RSS, notifications, and rollback/support expectations.
- **Launch blocker:** Not P0 if CI/deploy and operator runbooks are already green, but must be verified before inviting users.
- **Nice-to-have:** Smoke-test checklist, status page text, alert thresholds, and staged launch runbook.
- **Recommended next slice:** **Slice 114 – Launch Health Checklist and Smoke Paths**.
- **Priority:** P1 Livable Alpha Important.

## Launch blockers

Only true P0 items are listed here:

1. **Upload flow hardening:** uploaders need enough validation, guidance, and rejection recovery to submit without staff hand-holding.
2. **Account and tracker setup hub:** ordinary members need self-serve passkey/announce/client/RSS/account guidance.
3. **Seeder/demo data:** staff and reviewers need repeatable demo roles and realistic data to validate the alpha product.

## P1 Livable Alpha priorities

- Browse surface hardening.
- Torrent detail page hardening.
- RSS/watch/notification clarity.
- Staff moderation surface hardening.
- Admin/operations consolidation for launch use.
- Navigation and mobile information architecture pass.
- Contextual error and recovery states.
- Launch health checklist and smoke paths.

## P2 Polish / Later

- Visual identity polish beyond critical clarity fixes.
- Standardized empty-state component copy.
- Metadata label hierarchy refinements.
- Expanded screenshot/media presentation beyond the basic trust need.
- More account personalization and preference depth.

## Parked / Not now

These items should stay parked until the core tracker surface is livable:

- More discovery layers.
- Advanced recommendation tuning.
- Full reputation economy.
- Full community/social layer.
- Curator ecosystem expansion.
- AI-heavy automation.
- Cosmetic redesign without UX blocker value.
- Feature parity chase with old trackers.
- Badge/flag/column expansion that increases clutter.

## Recommended post-Slice-101 roadmap

1. **Slice 102 – Browse Surface Hardening:** improve browse readability, filter clarity, no-results recovery, and mobile-friendly result summaries.
2. **Slice 103 – Torrent Detail Page Hardening:** add practical trust/recovery affordances such as file list, report entry, copy helpers, and concise client guidance.
3. **Slice 104 – Upload Flow Hardening:** improve field guidance, preflight confidence, rejection recovery, and uploader next steps.
4. **Slice 105 – Account and Tracker Setup Hub:** centralize passkey, announce URL, RSS token, API keys, profile/security basics, and client setup.
5. **Slice 107 – Staff Moderation Surface Hardening:** make upload review more action-oriented and map report/user/content moderation gaps.
6. **Slice 113 – Livable Alpha Seeder and Demo Roles:** add representative roles, torrent states, uploads, notifications, RSS/watch examples, and operations states.
7. **Slice 110 – Mobile Alpha Pass:** convert the most important table-heavy surfaces into usable mobile summaries.
8. **Slice 111/112 – Empty and Error State Pass:** standardize no-results, validation, unauthorized, download-denied, and setup-recovery messaging.
9. **Slice 114 – Launch Health Checklist and Smoke Paths:** document and test the minimal product smoke paths CI/operators should trust before inviting users.

## Definition of done for future launch-surface slices

- The change improves a visible user, uploader, staff, or admin journey.
- The change does not create a new internal foundation layer unless directly required for that visible journey.
- P0/P1/P2/Parked classification is preserved in follow-up planning.
- Metadata reduces effort without adding badge overload.
- Tests or documentation-contract checks cover any durable launch contract.

## Slice 102 follow-up – Core user surface hardening

Date: 2026-06-19

Slice 102 moved the audit from discovery-foundation review into a narrow Livable Alpha product-surface pass. It did not add new discovery services, scoring engines, recommendation engines, or operations intelligence layers.

### Slice 101 gaps addressed

- **Front page / discovery home (P1):** added a concise ordinary-member orientation path from dashboard to browse, compare, detail, download, and follow.
- **Browse / torrent listing (P1):** made result copy user-facing, added subtle per-row metadata context, and improved no-results recovery with clear next actions.
- **Torrent detail page (P1):** added practical usage guidance, stronger download-denied recovery wording, and clearer missing metadata/description states.
- **Navigation (P1):** simplified primary labels for ordinary users by emphasizing Browse, Messages, and Ratio & snatches without adding new menu sprawl.
- **Empty/error states (P1/P2 overlap):** improved core no-results, missing catalog, missing metadata, missing description, and ratio-blocked recovery copy.

### Remaining launch gaps

- **Slice 103:** deeper torrent detail hardening remains, especially file list, report action, richer media context, and client/passkey guidance.
- **Slice 104:** upload flow remains a P0 launch blocker for preflight confidence, field guidance, and rejection recovery.
- **Slice 105:** account and tracker setup hub remains a P0 launch blocker for passkey, announce URL, RSS token, API keys, and preferences.
- **Slice 113:** Livable Alpha seeder/demo data remains a P0 launch blocker for realistic review journeys.

### Scope note

Metadata visibility was intentionally kept quiet: browse rows now show only compact release context where it helps selection, while detail pages keep metadata grouped instead of adding badge clutter.

## Slice 103 follow-up – Core tracker surface buildout

Date: 2026-06-20

Slice 103 rebuilt the core tracker pass cleanly after the abandoned PR #539 by keeping changes limited to visible, stable surfaces: browse scan labels and inspect actions, a detail-page release decision summary, upload readiness copy, and a conservative dashboard orientation panel.

This helps Livable Alpha by making the ordinary tracker loop easier to follow: browse, inspect, decide, download or follow, then upload and track account state without adding new backend systems or discovery machinery.

Slice 104 should focus on upload hardening: preflight confidence, clearer rejected-upload recovery, and uploader next steps while preserving the existing moderation workflow.


## Slice 104 follow-up – Staff admin launch operations surface

Date: 2026-06-20

Slice 104 hardened the existing staff/admin launch surfaces by clarifying the upload moderation queue, staff navigation labels, sysop operations readiness copy, and uploader-facing rejection recovery wording.

This matters for Livable Alpha because staff need to quickly see what needs review, what needs action, what is blocked, and which existing health and smoke paths should be checked before launch without adding a new operations engine.

A following launch-prep slice should focus on the account and tracker setup hub: passkey, announce URL, RSS token, API keys, and preference guidance for ordinary members.

## Slice 106 note — release readiness audit

Date: 2026-06-20

Slice 106 created a separate decision-support release readiness audit at [`docs/livable-alpha-release-readiness.md`](livable-alpha-release-readiness.md). It summarizes the current controlled-alpha go/no-go posture, launch blockers, pre-launch must-fix items, operating model, smoke checklist, deferred work, and recommended launch-focused next slices without changing product code.

## Slice 105 note — mobile visual alpha polish

- Applied a narrow polish pass to the existing dashboard, torrent browse/listing, torrent detail, upload, My Uploads, and staff moderation surfaces.
- Reviewed small-screen readability, button wrapping, table overflow affordances, empty/recovery copy, and tracker-critical actions: Browse, Inspect, Download/access guidance, Upload, My uploads, and staff review actions.
- This slice intentionally avoided discovery foundation work and backend expansion. The next launch-prep slice should still address first-use account/client setup and broader launch smoke readiness.
