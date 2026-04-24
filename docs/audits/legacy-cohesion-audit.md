# Legacy & Cohesion Audit

Date: 2026-04-24  
Repository: `nextgn_tracker`  
Scope: full repository static audit (`app/`, `routes/`, `resources/`, `config/`, `tests/`, `overlay/`, CI/workflow surface)

---

## Executive summary

This audit found a **mixed architecture state**: most active paths are modern Laravel-style slices, but there are still several legacy remnants and parallel implementations that increase maintenance risk.

### Top outcomes

- **Confirmed dead/unused candidates** were found (e.g., request classes, services, one Blade view, legacy overlay scaffold artifacts).
- **Duplicate logic** exists in key domains (markdown rendering, scrape handling, security event logging, ratio settings storage).
- **Cohesion issues** are primarily around mixed paradigms (old + new code paths co-existing), especially in tracker/security and settings layers.
- One **high-risk route definition issue** was found: a restore route appears declared without a controller action.

---

## Audit method

- Static repository scan of file inventory and cross-references (`rg`, `find`, targeted scripts).
- Route definition inspection in `routes/web.php` and `routes/api.php`.
- Class usage scan for probable unused classes (declaration-only or near declaration-only references).
- View usage scan by mapping Blade view names to `view(...)` references.
- Manual comparison of overlapping services/helpers in the same domain.

> Note: Runtime checks were attempted but blocked by dependency installation/network constraints (documented at the end).

---

## Confirmed dead/unused code candidates

### 1) Unused request class: `TorrentUploadRequest`
- **Evidence**: Upload flow uses `App\Http\Requests\Web\TorrentUploadStoreRequest` (which extends `StoreTorrentRequest`), while `TorrentUploadRequest` is not wired into controllers/routes.
- **Files**:
  - `app/Http/Requests/TorrentUploadRequest.php`
  - `app/Http/Controllers/TorrentUploadController.php`
  - `app/Http/Requests/Web/TorrentUploadStoreRequest.php`
- **Risk**: **Low**
- **Recommended action**: Remove after one focused PR that confirms no external/container binding references.
- **Removal status**: **Safe to remove now** (with one verification pass).

### 2) Unused request class: `UpdateTorrentStateRequest`
- **Evidence**: No route/controller currently type-hints this request; moderation routes use other request types.
- **Files**:
  - `app/Http/Requests/Admin/UpdateTorrentStateRequest.php`
  - `routes/web.php`
  - `app/Http/Controllers/TorrentModerationController.php`
- **Risk**: **Low**
- **Recommended action**: Remove in cleanup PR after confirming no planned near-term endpoint uses it.
- **Removal status**: **Safe to remove now**.

### 3) Unused service: `ScrapeService`
- **Evidence**: `ScrapeController` implements scrape result building inline and does not consume `ScrapeService`.
- **Files**:
  - `app/Services/ScrapeService.php`
  - `app/Http/Controllers/ScrapeController.php`
- **Risk**: **Low**
- **Recommended action**: Either remove `ScrapeService` or refactor controller to use it (pick one canonical path).
- **Removal status**: **Needs human review** (decide canonical architecture first).

### 4) Unused Blade view: `resources/views/admin/torrents/index.blade.php`
- **Evidence**: No controller returns `admin.torrents.index`; active moderation UI points to `staff.torrents.moderation.index`.
- **Files**:
  - `resources/views/admin/torrents/index.blade.php`
  - `app/Http/Controllers/TorrentModerationController.php`
- **Risk**: **Low**
- **Recommended action**: Remove if there is no upcoming admin-torrents page plan.
- **Removal status**: **Needs human review**.

### 5) Legacy overlay scaffold appears unused in current runtime
- **Evidence**: `overlay/` contains starter routes/views referencing older paths and a `TrackerController` pattern not part of active `routes/*.php`; only Dockerfile copy step mentions it.
- **Files**:
  - `overlay/routes/web.php`
  - `overlay/resources/views/home.blade.php`
  - `Dockerfile`
- **Risk**: **Medium**
- **Recommended action**: Decide whether `overlay/` is still deployment-critical; if not, remove in isolated PR.
- **Removal status**: **Needs human review**.

---

## Suspected legacy / ghost code candidates

### 6) Possible ghost tracker client validation path
- **Evidence**: `ClientValidator` and DTO `TrackerClientInfo` are not wired into active announce pipeline, which uses `AnnounceClientGuard`.
- **Files**:
  - `app/Services/Tracker/ClientValidator.php`
  - `app/Data/TrackerClientInfo.php`
  - `app/Tracker/Announce/AnnounceClientGuard.php`
- **Risk**: **Medium**
- **Recommended action**: Confirm if planned for future non-announce flow; otherwise remove pair together.
- **Removal status**: **Needs human review**.

### 7) Possible legacy sanitization/markdown stack remnants
- **Evidence**: `MarkdownRenderer`, `HtmlSanitizer`, and `Support\ContentSafety` exist beside actively used `MarkdownService` and `SanitizationService`; no active controller references to the former set.
- **Files**:
  - `app/Services/MarkdownRenderer.php`
  - `app/Services/HtmlSanitizer.php`
  - `app/Support/ContentSafety.php`
  - `app/Services/MarkdownService.php`
  - `app/Services/Security/SanitizationService.php`
- **Risk**: **Medium**
- **Recommended action**: choose one canonical content-safety stack and retire the other.
- **Removal status**: **Needs test coverage first** (content safety regressions are high-impact).

### 8) Middleware class remnants likely from older Laravel skeleton
- **Evidence**: Local middleware classes like `Authenticate`, `RedirectIfAuthenticated`, `VerifyCsrfToken`, `EnsureUserIsActive` are not explicitly wired in `bootstrap/app.php` aliases except selected middleware.
- **Files**:
  - `app/Http/Middleware/Authenticate.php`
  - `app/Http/Middleware/RedirectIfAuthenticated.php`
  - `app/Http/Middleware/VerifyCsrfToken.php`
  - `app/Http/Middleware/EnsureUserIsActive.php`
  - `bootstrap/app.php`
- **Risk**: **Medium**
- **Recommended action**: verify framework auto-wiring assumptions before deleting anything.
- **Removal status**: **Needs human review**.

---

## Duplicate or overlapping logic

### 9) Duplicate scrape aggregation logic
- **Overlap**: `ScrapeController` builds `files` scrape payload directly, while `ScrapeService::buildResponse()` provides similar logic.
- **Risk**: **Medium**
- **Action**: Consolidate to one implementation to avoid drift.
- **Removal status**: **Needs test coverage first**.

### 10) Duplicate security event persistence paths
- **Overlap**: `SecurityEventLogger` service and `AnnounceSecurityLogger` both create `SecurityEvent` entries with partially overlapping concerns.
- **Risk**: **Medium**
- **Action**: Introduce a single security logging abstraction and route announce logging through it (or intentionally document exception policy).
- **Removal status**: **Needs human review**.

### 11) Parallel audit/security log models with mixed usage
- **Overlap**: `AuditLog`, `SecurityEvent`, and `SecurityAuditLog` are all active with adjacent responsibilities, creating potential ambiguity in operational reporting.
- **Risk**: **Medium**
- **Action**: Define ownership boundaries (who logs what, when, and where) and document query/reporting strategy.
- **Removal status**: **Needs human review**.

### 12) Two ratio settings storage strategies
- **Overlap**: web admin ratio settings use `Setting`/`RatioSettings`; tracker admin API uses `SiteSetting`/`SiteSettingsRepository` + `RatioRulesConfig`.
- **Risk**: **High** (configuration divergence risk)
- **Action**: unify to one persisted settings source-of-truth for ratio behavior.
- **Removal status**: **Needs test coverage first**.

---

## Architectural cohesion issues

### 13) Route declaration anomaly: restore route appears incomplete
- **Evidence**: In `routes/web.php`, `Route::post('/posts/{post}/restore')->withTrashed()->name('posts.restore');` has no controller action attached.
- **Risk**: **High**
- **Action**: fix route wiring or remove if obsolete; add explicit feature test for restore endpoint behavior.
- **Removal status**: **Needs human review** (behavioral intent unclear).

### 14) Mixed moderation surface naming (`staff.*` + `moderation.uploads` alias)
- **Evidence**: Both `/staff/torrents/moderation` and `/moderation/uploads` map to moderation index behavior.
- **Risk**: **Low/Medium**
- **Action**: keep one canonical path and deprecate alias with redirect policy if needed.
- **Removal status**: **Needs human review**.

### 15) Repository has active app + legacy overlay app skeleton simultaneously
- **Evidence**: Main runtime routes/controllers live in `routes/` + `app/`; separate overlay routes/views suggest historical bootstrap layer.
- **Risk**: **Medium**
- **Action**: document if overlay is deployment-only artifact; otherwise remove to reduce cognitive overhead.
- **Removal status**: **Needs human review**.

---

## Suggested cleanup PR order

1. **PR 1 (low-risk deletions):** remove confirmed-unused request classes and unused blade (if product owner confirms no planned usage).  
2. **PR 2 (route correctness):** resolve/fix `posts.restore` route wiring + add endpoint test.  
3. **PR 3 (scrape cohesion):** consolidate scrape logic to either controller-only or service-only implementation.  
4. **PR 4 (content safety consolidation):** select canonical markdown/sanitization stack; remove duplicate helpers after regression tests.  
5. **PR 5 (settings convergence):** unify ratio settings persistence (`Setting` vs `SiteSetting`) behind one contract and migrate usages.  
6. **PR 6 (logging convergence):** rationalize `SecurityEventLogger`, `AnnounceSecurityLogger`, and `SecurityAuditLog` boundaries.  
7. **PR 7 (overlay decision):** retain-and-document overlay purpose or remove legacy scaffold directory.

---

## Checks attempted

Required checks requested:
- `composer lint`
- `composer test`
- `composer analyse`

### Environment/tooling outcome
- Dependency installation failed before checks could run due outbound GitHub access restrictions (`CONNECT tunnel failed, response 403`) while running `composer install`.
- As a result, lint/test/analyse are **not verified** in this environment.

---

## Confidence notes

- Findings marked **confirmed** are based on static reference scans and direct route/controller/view inspection.
- Findings marked **suspected/needs human review** may still be used through deployment conventions, framework auto-wiring, or planned near-term slices not inferable via static scan.
