# Livable Alpha Frontend & UX Readiness Audit

Date: 2026-05-31
Repository: `trackerone/nextgn_tracker`
Scope: frontend and UX readiness only; no implementation changes were made.

## Executive summary

NextGN has a meaningful backend foundation and several useful Laravel Blade surfaces, but it does **not yet feel like a livable alpha product** for real invited users. It currently feels like a partially finished tracker prototype with strong domain services behind it, a reasonable torrent browse/details/upload slice, and several backend/API-first areas that are either unreachable, JSON-only, or styled like internal tools rather than a coherent product.

The biggest alpha-launch risk is not that every feature is missing. The risk is that a real user will hit walls quickly because major promised journeys are only partially surfaced:

- The public landing page is effectively the login page because `/` redirects to `/login`; there is no product orientation for guests.
- Forum and private-message web routes return JSON, while the only React forum/PM application is mounted in an apparently unused `welcome.blade.php` shell. This makes navigation links like Forum and PM feel broken for normal browser users.
- Account/profile/settings are fragmented. Users can access RSS, ratio, notifications, follows, invites, and API keys routes, but there is no coherent profile/settings hub and the API keys route is JSON-only.
- Admin and staff tooling exists, but admin dashboard and user/API management are mostly backend/API slices rather than a coherent administrator product.
- Moderation has a useful upload queue and approve/reject/soft-delete flow, but report handling, broader content management, user moderation, and escalation workflows are absent or not discoverable.
- Several screens use a polished shared Blade layout, while admin invite/ratio/metadata settings use standalone inline CSS pages. The result is visually inconsistent and operationally confusing.

**Bottom line:** Do not invite real alpha users tomorrow unless the scope is explicitly limited to a supervised torrent browse/upload/moderation demo. For a normal daily-user alpha, there are critical blockers around community navigation, account settings, passkey/API-token visibility, admin completeness, and product coherence.

## Method and evidence reviewed

This audit inspected the Laravel routes, controllers, Blade templates, React entrypoint/components, composer/npm scripts, and visible frontend files. Runtime browser validation was blocked because Composer dependencies are not installed (`vendor/autoload.php` is missing), so findings are based on static source review plus attempted route inspection.

Key inspected areas:

- `routes/web.php` and `routes/api.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/auth/*.blade.php`
- `resources/views/home.blade.php`
- `resources/views/torrents/*.blade.php`
- `resources/views/account/*.blade.php`
- `resources/views/staff/torrents/moderation/index.blade.php`
- `resources/views/admin/**/*.blade.php`
- `resources/views/sysop/operations/index.blade.php`
- `resources/js/app.tsx` and forum/PM React components
- Relevant controllers for forum, PM, API keys, admin dashboard, and torrent flows

## 1. User journey audit

### Guest

| Journey | Status | Findings |
| --- | --- | --- |
| Landing page | **Partially complete / unclear** | `/` redirects directly to `/login`, so the login page doubles as marketing/landing. It communicates the product concept, but guests do not get a real landing/onboarding path, FAQ, invite explanation, rules, status, or alpha expectations. |
| Login | **Mostly complete** | Login page is polished, has clear value messaging, error block, email/password fields, and registration link. Missing password reset, remember-me, account-disabled state, rate-limit guidance, and support contact. |
| Registration / invite flow | **Partially complete** | Invite code is collected and errors render inline. The UX does not explain where to obtain an invite beyond “staff,” has no success orientation, no rules acceptance, no email-verification guidance, and the invite field is not required in local only, which is fine for dev but confusing if surfaced in non-production staging. |
| Browse experience | **Missing for guests** | Torrent browse is auth-only. That can be intentional for a private tracker, but unauthenticated users get no teaser, rules, or closed-community explanation beyond login copy. |
| Torrent details viewing | **Missing for guests** | Details are auth-only. Acceptable for a private tracker, but guests have no clear “what you will get after invite” preview. |

### Member

| Journey | Status | Findings |
| --- | --- | --- |
| Dashboard / home | **Partially complete** | Dashboard is one of the stronger screens: stats, recent torrents, recent topics, messages, and CTAs exist. However, forum/message cards are read-only summaries, not full daily-use workflows. Ratio values are raw bytes, not humanized. |
| Browse | **Partially complete** | Browse has search, filters, grouped/flat views, metadata badges, seed/leech/completed counts, pagination, and empty state. UX is dense, table-heavy, and horizontally scrolls on mobile. Advanced metadata facets are display-only labels, which can mislead users into thinking language/subtitles/codec/HDR/audio filters exist. |
| Search | **Partially complete** | Search supports text and tracker-style tokens (`rg:`, `source:`, `res:`, `year:`) but lacks inline validation, saved searches, result-count context beyond pagination, typo/no-result guidance, and mobile-friendly filter collapse. |
| Filtering | **Partially complete** | Type/resolution/source/category/order/direction are available. Missing many fields that the UI hints at: language, subtitles, codec, HDR, audio, freeleech, seed availability, uploader, year as first-class control. |
| Torrent details | **Mostly complete** | Details show download/magnet/follow actions, eligibility messaging, quick facts, metadata, stats, links, description, and NFO. Missing screenshots/media preview, comments/discussion, file list, uploader profile trust, report button, bookmark/favorite, and stronger empty/error states. |
| Download flow | **Partially complete** | `.torrent` download and magnet action are present behind policy checks. Eligibility messaging exists, but the user is not guided to install/configure their client, retrieve passkey, understand announce URL, or recover from failed downloads. Magnet fetch exposes a text block but no copy button. |
| Passkey visibility | **Missing / critical** | There is no obvious account page showing tracker passkey, announce URL, reset/regenerate action, or client setup instructions. RSS explicitly says RSS tokens are separate from tracker passkeys, but users cannot find the tracker passkey in the frontend. |
| Profile/settings | **Mostly missing** | There is no coherent profile/settings page. Account routes exist for RSS, notifications, watch presets, snatches, invites, and API keys, but the primary nav does not include Invites or API Keys, and API Keys is JSON-only. No password/email/profile/preferences/privacy settings are visible. |
| Notifications | **Partially complete** | Notifications list, mark-read, mark-all-read, empty state, and torrent link exist. The scope is narrow: torrent watch preset matches only. No global unread badge in nav, no preference center, no delivery options, and no moderation/private-message integration visible. |
| RSS functionality | **Partially complete** | RSS token generation/rotation, personal feed URL, warning, presets, and edit/delete links exist. UX lacks copy buttons, client setup examples, feed test/preview, per-preset validation feedback clarity, and “last fetched” or failure diagnostics. |
| Bookmarks/favorites/watchlists | **Partially complete / confusing** | “Follows” and “Watch Presets” exist, but no simple bookmark/favorite/watchlist action exists. “Follow with metadata” is present on details, yet users may not understand whether this is a favorite, saved search, title follow, or notification rule. |
| Forum/community | **Broken for browser users** | The nav links to `/topics`, but that route returns JSON. The React forum UI mounts only in `welcome.blade.php`, which is not routed from `/topics`. A normal user clicking Forum likely sees JSON, not a usable page. |
| Private messages | **Broken for browser users** | The nav links to `/pm`, but the controller returns JSON. The React PM panel is mounted in the same apparently unused React app shell as the forum. A normal user clicking PM likely sees JSON. |

### Uploader

| Journey | Status | Findings |
| --- | --- | --- |
| Upload entry | **Partially complete** | Primary nav and dashboard CTA expose upload. |
| Upload form | **Partially complete** | Form supports `.torrent`, category, type, source, resolution, tags, video/audio codecs, description, NFO file/text, and moderation disclaimer. It is visually acceptable but basic. |
| Validation feedback | **Partially complete** | Global error list and one inline torrent-file error exist; most fields do not render field-level errors. There is no client-side progress, preflight preview step, duplicate explanation after submission, or accessible error focus management. |
| Metadata handling | **Partially complete** | The form collects basic metadata and post-submit details can show normalized metadata. However, uploaders cannot preview parsed torrent contents, file list, inferred metadata, duplicate/upgrade comparison, screenshots, or enrichment results before final submission. |
| Screenshots/media | **Missing** | No screenshot upload/display flow is visible. Torrent details have no media/screenshot area. For media trackers, this is a major trust/completeness gap. |
| Publishing flow | **Partially complete** | Submissions go to moderation, and “My uploads” tracks pending/rejected/published with reason. Uploaders cannot revise rejected uploads, resubmit, withdraw, message moderators, or see a structured checklist of required fixes. |
| Post-upload experience | **Partially complete** | My Uploads is clear and has empty state. It lacks next-step guidance after pending/rejection, direct edit/resubmit action, and richer audit timeline. |

### Moderator

| Journey | Status | Findings |
| --- | --- | --- |
| Moderation queue | **Mostly complete for uploads only** | Pending upload table includes uploader, category/type, metadata review, completeness, dates, actions, and recently moderated list. This is useful but dense and table-heavy. |
| Approval/rejection flow | **Partially complete** | Publish, reject with required reason, and soft-delete exist. There is JS to disable buttons and confirm destructive actions. Missing bulk actions, claim/assign, detailed review checklist, compare duplicate/version UI, safe preview/download for moderators, and escalation notes. |
| Reports | **Missing** | No report routes or frontend report UI were found for torrents, posts, PMs, or users. |
| Content management | **Partially complete** | Staff can moderate uploads and forum moderation endpoints exist for lock/pin/delete/restore, but the browser-facing forum UI appears unreachable. There is no unified content management dashboard. |
| User moderation actions | **Mostly backend/admin only or missing** | There is a user role patch route, but no visible user management frontend. No ban/suspend/warn/invite revoke/user activity view is visible. |

### Administrator

| Journey | Status | Findings |
| --- | --- | --- |
| Admin dashboard | **Mostly backend only / placeholder** | `/admin` returns JSON `{"message":"Admin area"}` after logging an audit event. It is not a usable dashboard. |
| System visibility | **Partially complete** | Sysop operations page exists with health cards and runtime job visibility/toggles. Audit/security logs have views. This is useful for operators, not enough for normal administrators. |
| Settings management | **Partially complete / inconsistent** | Ratio and metadata settings exist, but they are standalone inline-CSS pages, not integrated with the main app layout/navigation. Metadata settings use JS-backed API calls, but the page feels internal. |
| Invite management | **Partially complete / inconsistent** | Admin invite generation and filtering exist, also standalone inline-CSS page. It is functional but not cohesive. |
| Operational tooling | **Partially complete** | Runtime job controls, logs, settings, and some API settings exist. Missing dashboard overview, user management UI, content/report queues, storage/queue/mail health drill-downs, and alpha support tooling. |

## 2. Frontend screen inventory

| Screen | Exists? | Readiness | UX notes |
| --- | --- | --- | --- |
| Login | Yes | **Mostly production-ready** | Polished layout and messaging. Missing password reset/support/rate-limit specifics. |
| Register | Yes | **Partially ready** | Clean form, but minimal invite education and missing account/rules onboarding. |
| Authenticated layout/nav | Yes | **Partially ready** | Cohesive dark styling, but too many top-level horizontal nav items and important routes omitted. Mobile relies on horizontal scroll with no menu hierarchy. |
| Dashboard | Yes | **Partially ready** | Strong first impression, but some cards link to broken/incomplete journeys and stats are not fully user-friendly. |
| Torrent browse | Yes | **Partially ready** | Functional and metadata-aware, but dense, table-driven, limited mobile usability, and hints at unavailable filters. |
| Torrent details | Yes | **Mostly ready** | Strongest user-facing torrent screen. Missing screenshots, comments, file list, report/bookmark, passkey/client guidance. |
| Torrent upload | Yes | **Partially ready** | Basic uploader path exists. Missing preview, screenshot support, field-level validation, progress, and resubmission/edit loop. |
| My uploads | Yes | **Partially ready** | Useful status table and empty state. Missing edit/resubmit/withdraw and moderation timeline. |
| For You | Yes | **Partially ready** | Good empty states and follows integration. Limited explainability and no tuning controls beyond follows. |
| My follows | Yes | **Partially ready** | Manual follow form and match lists exist. No edit/delete controls are visible, and vocabulary overlaps confusingly with Watch Presets. |
| Notifications | Yes | **Partially ready** | Basic notification list works. No nav unread count/preferences and narrow notification scope. |
| RSS feeds | Yes | **Partially ready** | Token/presets exist. Needs copy/test/client setup UX. |
| RSS preset form | Yes | **Partially ready** | Exists, not deeply audited; likely utilitarian. |
| Watch presets | Yes | **Partially ready** | Exists, but concept competes with follows/RSS presets and is not clearly introduced in primary user flow. |
| Ratio/snatches | Yes | **Partially ready** | Useful accounting, but raw bytes and no tracker-client/passkey guidance. |
| Account invites | Yes | **Partially ready** | Read-only invite inventory; no creation/request path for normal users. Hidden from primary nav. |
| Account API keys | Route only | **Backend/API only** | Web route points to JSON controller. No browser UI. |
| Forum | Route/API + React components | **Broken / unreachable as product UI** | `/topics` returns JSON; React forum app is mounted in an unused shell. |
| Private messages | Route/API + React components | **Broken / unreachable as product UI** | `/pm` returns JSON; React PM panel is mounted in unused shell. |
| Staff upload moderation | Yes | **Partially ready** | Useful table and actions. Dense; upload-only; no reports/users/content dashboard. |
| Admin dashboard | Route only | **Placeholder/backend only** | JSON placeholder, not product UI. |
| Admin invites | Yes | **Partially ready internal tool** | Functional but standalone inline CSS, disconnected from app chrome. |
| Admin ratio settings | Yes | **Partially ready internal tool** | Functional but standalone inline CSS, disconnected from app chrome. |
| Admin metadata settings | Yes | **Partially ready internal tool** | JS/API-backed, standalone styling, no app chrome. |
| Audit/security logs | Yes | **Partially ready internal tool** | Views exist; not deeply audited. |
| Sysop operations | Yes | **Partially ready** | Integrated with main layout and useful, but sysop-specific. |
| Error pages | Yes | **Partially ready** | 404/500/503 exist; not enough evidence of route-specific helpful recovery. |

### Layout, responsiveness, states, and accessibility notes

- Layout is mostly consistent in the member Blade area, but admin/settings pages break the system with standalone HTML and inline CSS.
- Primary navigation is a long horizontal list. It will become hard to scan as roles/features grow, and on mobile it hides hierarchy behind horizontal scrolling.
- Table-heavy screens use `overflow-x-auto`, which avoids broken layout but does not create a good mobile experience.
- Empty states exist in several good places: dashboard recent torrents, My Uploads, For You, Follows, RSS presets, Notifications, Snatches, Moderation queue.
- Loading states are mostly absent in server-rendered screens. React forum/PM has loading text, but those UIs appear unreachable from normal navigation.
- Error states are mostly global Blade error lists. Field-level errors are inconsistent.
- Accessibility is mixed: some labels and `aria-live` are present, but many action clusters rely on color/status pills, tables are dense, destructive actions use `window.confirm`, and there is no obvious focus management after validation errors or async magnet failures.

## 3. Alpha blocker identification

### Critical — cannot launch livable alpha

1. **Forum and PM navigation are broken for normal browser users.** The primary nav exposes Forum and PM, but `/topics` and `/pm` return JSON. The React app that renders forum/PM mounts in `welcome.blade.php`, which is not used by those routes.
2. **No visible tracker passkey/client setup path.** Users cannot realistically configure a torrent client without a passkey/announce URL/help screen. RSS says its token is separate, which makes the missing passkey even more obvious.
3. **No coherent account/profile/settings hub.** A daily user cannot manage identity, password/email, passkey, API keys, notification preferences, or tracker-client setup from one discoverable place.
4. **Admin dashboard is a JSON placeholder.** Administrators do not have a real product dashboard for alpha operations.
5. **Reports and abuse/moderation intake are absent.** Users cannot report torrents, posts, PMs, or users; moderators cannot triage reports.
6. **API Keys web route is JSON-only.** A nav-discoverable account management surface is missing for a security-sensitive feature.

### High — alpha possible only with severe degradation

1. **Upload flow lacks preview/resubmission loop.** Uploaders submit blind, cannot preview parsed files/metadata, cannot attach screenshots, and cannot revise rejected uploads from the UI.
2. **Screenshots/media evidence are missing.** For a media tracker, torrent trust is severely reduced without screenshots or visual proof.
3. **Admin/settings pages are visually disconnected internal tools.** Staff/admin users will feel like they are jumping between products.
4. **Moderation is upload-only.** There is no unified moderation home for reports, users, forum posts, PM issues, or content search.
5. **Follows / Watch Presets / RSS Presets are conceptually confusing.** Users face multiple similarly named automation mechanisms without a clear mental model.
6. **Mobile usability is weak on table-heavy screens.** Browse, My Uploads, Moderation, Snatches, Admin pages will be awkward on phones.

### Medium — noticeable UX problems

1. Search and filters lack inline validation, result explanation, and guidance when no results match.
2. Magnet link flow has no copy button and uses a raw hidden text box interaction.
3. Ratio/accounting uses raw bytes in several places instead of human-readable units.
4. Notifications have no unread count in primary nav and cover only watch preset matches.
5. My Follows has no visible edit/delete/manage controls.
6. Torrent details lack file list, comments, uploader trust/history, and related versions navigation.
7. Error handling is mostly global and not field-focused.
8. Destructive actions rely on browser confirm dialogs instead of consistent app dialogs.

### Low — polish issues

1. Terminology varies: “For You,” “Follows,” “Watch Presets,” “RSS Presets,” “Notifications,” “Ratio.” It needs a clearer information architecture.
2. Admin standalone pages lack shared header/footer, nav, breadcrumbs, and flash/error styling consistency.
3. Several pages use technical labels (`S`, `L`, `C`, “Row metadata kept secondary”) that assume tracker expertise.
4. CTA hierarchy is sometimes overloaded, especially on torrent details and dashboard.

## 4. Backend vs frontend gap analysis

| Backend/API/route exists | Frontend gap | Impact |
| --- | --- | --- |
| Forum routes for topics/posts | `/topics` and `/topics/{slug}` return JSON, while React UI is mounted only in unused `welcome.blade.php`. | Forum appears broken to browser users. |
| Private message routes | `/pm` and `/pm/{conversation}` return JSON, while PM React panel is mounted only in unused shell. | PM appears broken to browser users. |
| API key routes under `/account/api-keys` | Controller returns JSON for index/store/delete; no Blade/React UI. | Users cannot manage API keys in the browser. |
| Admin dashboard route `/admin` | Controller returns JSON placeholder. | No admin home or operational summary. |
| User role update route | No visible user management/list/detail UI. | Admin cannot discover or safely manage users. |
| Staff moderation endpoints | Upload queue UI exists, but no report queue, user moderation UI, claim/assign, or audit timeline UI. | Moderation readiness is upload-only. |
| RSS feed/token/preset routes | UI exists, but lacks copy/test/setup UX and diagnostics. | Feature is technically present but hard to use safely. |
| Watch preset routes | UI exists, but nav/concept overlaps with follows and RSS presets. | Users may not understand which automation to use. |
| Metadata provider/credential APIs | Admin metadata page exists but is standalone inline CSS/JS, outside app layout. | Settings are not productized. |
| Ratio settings API/web | Settings page exists but standalone inline CSS, outside app layout. | Admin UX inconsistency. |
| Torrent metadata extraction/enrichment services | Details and browse show metadata, but upload preview, screenshots, file list, and correction flows are missing. | Backend value is not fully surfaced to uploaders/users. |
| Download eligibility service | Eligibility message exists on details, but passkey/client setup and recovery guidance are missing. | Users may know they can download but not how to actually use the tracker. |

## 5. UI consistency review

NextGN currently feels like a **partially finished product with a developer-prototype/admin-tool seam**.

What feels cohesive:

- Member-facing Blade pages share a dark Tailwind visual language.
- Dashboard, browse, details, upload, notifications, RSS, follows, and moderation use similar card/table patterns.
- Status pills, brand color, border treatments, and dark panels are mostly consistent in the member/staff app.

What breaks cohesion:

- Admin invite, ratio, and metadata settings pages are standalone documents with inline CSS and no shared app chrome.
- Forum/PM React app is architecturally disconnected from web routes and likely unreachable.
- Navigation is flat and overloaded; it mixes product areas, account functions, and role tools in one horizontal row.
- Account features are scattered rather than grouped under a user settings/profile area.
- Some screens are polished enough to promise a real product, while others reveal backend/API-first implementation immediately.

Discoverability issues:

- Invites and API Keys are routed but not in primary nav.
- Passkey is not discoverable at all.
- Admin settings are reachable only if users know URLs or role-specific links not shown in the main nav.
- “Watch Presets” is a top-level nav item, but “Invites” and “API Keys” are not, which feels arbitrary.

## 6. Alpha readiness score

| Category | Score | Explanation |
| --- | ---: | --- |
| Backend Foundation | **7/10** | Strong domain coverage for torrents, metadata, upload eligibility, moderation, RSS, notifications, ratio, invites, and operations. The backend is ahead of the UI. Some admin/report/profile surfaces are incomplete. |
| Frontend Completeness | **4/10** | Core torrent browse/details/upload exists, but forum/PM/API keys/admin dashboard are JSON-only or unreachable; screenshots, passkey, profile/settings, reports, and user management are missing. |
| UX Readiness | **3/10** | Several screens are usable, but daily-user flows break around setup, community, account management, upload revision, and mental model clarity. Mobile/table UX is weak. |
| Moderation Readiness | **4/10** | Upload moderation queue is useful. Broader moderation (reports, users, forum/PM abuse, content management, escalation) is not alpha-ready. |
| Operational Readiness | **5/10** | Sysop operations and logs exist, but admin dashboard, user tooling, support workflows, and cohesive settings management are insufficient for invited alpha operations. |
| Overall Livable Alpha Readiness | **3.5/10** | Good backend plus partial member UI, but too many real-user walls remain. Suitable for an internal demo, not a normal daily-use alpha. |

## 7. Livable Alpha checklist

### Must Fix Before Alpha

1. **Make Forum and PM real browser pages or remove them from alpha nav.** Do not link users to JSON.
2. **Add account settings/profile hub with passkey, announce URL, client setup, password/email basics, and API key management.**
3. **Create a usable admin dashboard.** Include users, invites, moderation, reports, system health, logs, and settings entry points.
4. **Add report flow and report moderation queue.** Minimum: report torrent/user/forum post/PM, triage, resolve, audit trail.
5. **Clarify and consolidate account automation concepts.** Define Follows vs Watch Presets vs RSS Presets; make the right default path obvious.
6. **Add upload preview and rejection recovery.** Minimum: parsed torrent summary, metadata preview, field-level errors, rejected upload fix/resubmit path.
7. **Add passkey/client onboarding to download flow.** Users need setup instructions before/after first download.
8. **Unify admin/settings pages under the shared layout.** Remove standalone internal-tool feel for alpha-facing staff/admin work.
9. **Ensure nav only exposes functional product surfaces.** Hide or relabel incomplete JSON/backend-only endpoints.

### Should Fix Before Alpha

1. Add screenshot/media support for uploads and torrent details.
2. Add file list to torrent details.
3. Add copy buttons for magnet links, RSS URLs, passkeys, announce URLs, and API keys.
4. Add unread notification count to primary nav.
5. Add edit/delete/manage controls for follows and presets.
6. Improve mobile browse/details/upload/moderation layouts beyond horizontal table scroll.
7. Add field-level validation consistently across upload, follows, presets, invites, settings, and moderation forms.
8. Humanize ratio/storage units everywhere.
9. Add breadcrumbs or section headings for account/admin/staff areas.
10. Add support/contact/rules links during login/register and account onboarding.

### Can Wait Until Beta

1. Advanced saved searches and browse personalization beyond follows.
2. Bulk moderation actions.
3. Rich notification preferences and delivery channels.
4. Full media galleries and screenshot moderation workflows.
5. Advanced analytics dashboards.
6. Polished custom modals replacing every browser confirm.
7. User profile pages and reputation/trust presentation beyond minimum admin/user management.
8. Deep RSS diagnostics such as last fetch, client status, and per-client setup recipes.

## 8. Recommended next development order

1. **Stop broken navigation first.** Route Forum and PM to real pages that mount React, or temporarily remove them from nav for alpha.
2. **Build the account setup spine.** Profile/settings hub with passkey, announce URL, API keys, password/email, RSS entry, notifications, and client setup.
3. **Close first-download journey.** From details: eligibility → passkey/client setup → `.torrent`/magnet → help if blocked.
4. **Make upload lifecycle livable.** Parsed preview, screenshots, clear validation, post-submit status, rejection fix/resubmit.
5. **Add report and moderation intake.** Report buttons plus staff queue and resolution states.
6. **Productize admin.** Real admin dashboard and shared-layout settings/invites/logs/user management links.
7. **Simplify information architecture.** Group account automations and reduce top-level nav overload.
8. **Improve mobile and accessibility.** Replace critical tables with cards where needed, improve focus/error management, add copy buttons and better async states.
9. **Polish torrent trust surfaces.** File list, screenshots, uploader context, related versions, comments/discussion integration.

## Final alpha call

If real users were invited tomorrow, they would immediately hit walls in these places:

1. Clicking Forum or PM from the nav and seeing JSON instead of a product page.
2. Trying to configure a torrent client and not finding passkey/announce setup.
3. Trying to manage account settings/API keys/profile and finding fragmented or JSON-only surfaces.
4. Uploading a torrent and not being able to preview parsed content, attach screenshots, or fix a rejected submission.
5. Trying to report bad content or contact moderators and finding no report workflow.
6. Admins trying to operate the alpha and finding a JSON admin dashboard plus disconnected settings pages.

**Recommendation:** Treat the current state as **internal alpha/demo-ready**, not **livable alpha-ready**. The next milestone should be a narrow “daily-use spine” that makes browse → detail → setup/download → follow/notify → upload/moderate → report/admin support coherent before inviting real users.
