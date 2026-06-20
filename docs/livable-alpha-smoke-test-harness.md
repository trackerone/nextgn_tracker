# Livable Alpha Smoke Test Harness

## 1. Purpose

This is the repeatable manual test harness for controlled Livable Alpha.
Staging/alpha is the go/no-go environment.
Run it before invites and during alpha operations.
Record failures and triage them into follow-up slices or tasks.
Do not treat local-only success as launch approval.

## 2. Environments

| Environment | Purpose | Go/no-go authority |
| --- | --- | --- |
| Local/dev | Development sanity only | No |
| Staging/alpha | Real alpha verification | Yes |
| Production alpha candidate | Final pre-invite check | Yes |

Notes:

- Local green is not enough.
- Staging/alpha must use realistic roles, storage, secrets, and data.

## 3. Required roles

| Role | Must access | Must not access |
| --- | --- | --- |
| Guest | Public/login surfaces only | Private tracker pages, torrent browse, torrent detail, downloads, RSS feeds, upload, moderation, operations |
| Member | Dashboard/home, eligible browse/detail/download, account RSS setup, allowed RSS feeds, watch/follow/preset paths if present | Staff moderation, sysop/admin operations, ineligible downloads, private pages after logout |
| Uploader | Member surfaces, upload form, upload submission, My Uploads, own pending/rejected/approved upload status | Staff moderation, unrelated users' private upload controls, sysop/admin operations |
| Staff moderator | Moderation queue/actions, upload review, approve/reject, supported soft-delete, relevant moderation history/log surfaces | Unrelated sysop-only operations/security areas |
| Sysop/admin | Operations, health, logs/audit/security surfaces, role/configuration inspection where available | None within assigned admin scope; verify least-privilege boundaries for lower roles |

## 4. Required validation data

- [ ] Approved visible torrent
- [ ] Torrent with strong metadata
- [ ] Torrent with missing metadata
- [ ] Allowed download case
- [ ] Blocked download case
- [ ] Valid `.torrent` upload fixture
- [ ] Invalid upload fixture
- [ ] Pending upload
- [ ] Rejected upload with reason
- [ ] Approved upload
- [ ] Member with RSS token
- [ ] Member after RSS token rotation
- [ ] RSS feed with at least one visible item
- [ ] Watch/follow/preset example if present
- [ ] Staff account
- [ ] Sysop/admin account

## 5. Smoke test matrix

| Area | Role | Action | Expected result | Blocks alpha? | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Auth/session | Guest | Open dashboard/home. | Redirected to login or public auth flow; no private data shown. | Yes |  |  |
| Auth/session | Guest | Open torrent browse. | Redirected away from private tracker page; no browse data shown. | Yes |  |  |
| Auth/session | Guest | Open torrent detail. | Redirected away from private tracker page; no torrent data shown. | Yes |  |  |
| Auth/session | Member | Log in. | Member reaches dashboard/home with expected member navigation. | Yes |  |  |
| Auth/session | Member | Log out. | Session ends and user lands on expected logged-out/auth page. | Yes |  |  |
| Auth/session | Member | Attempt private page after logout. | Redirected to login; no private page remains accessible. | Yes |  |  |
| Member browse/detail | Member | Open dashboard/home. | Core tracker actions are visible and understandable. | Yes |  |  |
| Member browse/detail | Member | Browse torrents. | Approved visible torrents are listed for the member. | Yes |  |  |
| Member browse/detail | Member | Search by text. | Matching results appear, or a recoverable no-results state appears. | Yes |  |  |
| Member browse/detail | Member | Apply at least one filter. | Results update according to the selected filter. | Yes |  |  |
| Member browse/detail | Member | Clear/recover from no-results state. | Member can return to usable results without staff help. | Yes |  |  |
| Member browse/detail | Member | Open torrent detail. | Detail page loads for an eligible visible torrent. | Yes |  |  |
| Member browse/detail | Member | Read metadata/description/NFO area. | Available metadata, description, and NFO areas are readable; missing metadata is clearly indicated. | Yes |  |  |
| Member browse/detail | Member | See release decision/download state. | Member can tell whether download is allowed, blocked, or unavailable. | Yes |  |  |
| Download/magnet | Eligible member | Download `.torrent`. | File download starts or returns the expected torrent response. | Yes |  |  |
| Download/magnet | Eligible member | Open magnet link if present. | Magnet link is visible and usable, or absence is clear. | No |  |  |
| Download/magnet | Ineligible/restricted member | View blocked download state. | Download is blocked with safe recovery copy; no file or token leaks. | Yes |  |  |
| Download/magnet | Eligible member | Trigger missing torrent file/storage case if already supported. | Failure is safe, logged where applicable, and does not expose private paths/secrets. | Yes |  |  |
| Upload/My Uploads | Uploader | Open upload form. | Form loads with required fields and guidance. | Yes |  |  |
| Upload/My Uploads | Uploader | Submit invalid upload. | Validation fails safely with actionable errors. | Yes |  |  |
| Upload/My Uploads | Uploader | Submit valid `.torrent`. | Upload is accepted into the expected moderation/status flow. | Yes |  |  |
| Upload/My Uploads | Uploader | Open My Uploads. | Uploader sees own uploads only. | Yes |  |  |
| Upload/My Uploads | Uploader | See pending status. | Pending upload status is visible and understandable. | Yes |  |  |
| Upload/My Uploads | Uploader | See rejected reason. | Rejected upload displays staff reason and next step. | Yes |  |  |
| Upload/My Uploads | Uploader/Member | Confirm approved upload becomes visible where expected. | Approved upload appears in browse/detail for eligible users. | Yes |  |  |
| Staff moderation | Staff moderator | Open moderation queue. | Pending uploads are visible with review actions. | Yes |  |  |
| Staff moderation | Staff moderator | Approve pending upload. | Upload moves to approved state and becomes visible where expected. | Yes |  |  |
| Staff moderation | Staff moderator | Reject pending upload with reason. | Upload moves to rejected state and uploader can see reason. | Yes |  |  |
| Staff moderation | Staff moderator | Soft-delete only where existing behavior supports it. | Supported soft-delete action works safely; unsupported behavior is not forced. | No |  |  |
| Staff moderation | Member | Attempt to access staff moderation. | Access is denied or redirected; no moderation data shown. | Yes |  |  |
| Staff moderation | Member | Attempt moderation actions. | Action is denied; no upload state changes. | Yes |  |  |
| RSS/watch | Member | Open account RSS setup. | RSS setup/token controls load for the member. | Yes |  |  |
| RSS/watch | Member | Open tokenized RSS feed. | Feed loads only with valid token and includes expected visible item. | Yes |  |  |
| RSS/watch | Member | Rotate RSS token on test account. | Token changes and user can identify current token/feed. | Yes |  |  |
| RSS/watch | Member | Verify old/new token behavior. | New token works; old token fails safely or behaves as documented. | Yes |  |  |
| RSS/watch | Eligible member | Download through RSS route. | Eligible RSS download succeeds without exposing extra data. | Yes |  |  |
| RSS/watch | Ineligible/restricted member | Verify RSS/download failure if supported. | Ineligible access fails safely; token does not bypass eligibility. | Yes |  |  |
| RSS/watch | Member | Check watch/follow/preset path if present. | Path loads and can be understood without breaking RSS/browse flow. | No |  |  |
| Operations/security | Sysop/admin | Open operations/health surface if present. | Surface loads and shows actionable runtime status. | Yes |  |  |
| Operations/security | Sysop/admin | Open logs/audit/security surface if present. | Surface loads and shows relevant recent events. | Yes |  |  |
| Operations/security | Staff/member | Verify access boundaries. | Staff/member cannot access sysop-only operations/security areas. | Yes |  |  |
| Operations/security | Guest | Verify private torrent pages are not exposed. | Guest cannot see private browse/detail/download content. | Yes |  |  |
| Operations/security | Staff/sysop | Review RSS/download/upload/moderation security logs if present. | Relevant events are visible or logging gap is recorded. | No |  |  |
| Mobile/readability | Member | Browse on phone-sized viewport. | Browse remains readable and primary actions remain usable. | No |  |  |
| Mobile/readability | Member | Torrent detail on phone-sized viewport. | Detail remains readable and download/state actions remain visible. | No |  |  |
| Mobile/readability | Uploader | Upload form on phone-sized viewport. | Required fields and submit action remain usable. | No |  |  |
| Mobile/readability | Uploader | My Uploads on phone-sized viewport. | Status and rejection/approval details remain readable. | No |  |  |
| Mobile/readability | Staff moderator | Staff moderation on narrow viewport if relevant. | Queue and actions remain usable enough for staff. | No |  |  |
| Mobile/readability | Member/Uploader/Staff | Confirm primary actions remain visible and usable. | Critical actions do not disappear behind broken layout. | Yes |  |  |

## 6. Go/no-go rules

Launch may proceed only if:

- All rows marked "Blocks alpha? Yes" pass.
- No private access, token, or download leak is found.
- Member browse/detail/download works for an eligible account.
- Staff can approve/reject without database edits.
- Upload and My Uploads flow works for a trusted uploader.
- RSS feed/download works safely or is explicitly disabled/deferred.
- Non-blocking issues are recorded.

Launch must stop if:

- Eligible member cannot complete browse/detail/download.
- Staff cannot moderate.
- Upload cannot be reviewed safely.
- RSS/download token behavior leaks access.
- Auth/session/private pages are unsafe.
- Alpha requires manual database edits to function.

## 7. Failure recording template

Enter smoke-test failures into the alpha feedback intake so staff can triage them with the same severity and blocker language used by this harness. Use `blocker`, `must-fix`, or `non-blocking` for severity and mark `Blocks alpha` consistently with the smoke row.

```text
Date/time:
Environment:
Role:
Area:
Steps to reproduce:
Expected:
Actual:
Screenshot/log link:
Severity: blocker / must-fix / non-blocking
Blocks alpha: yes / no
Owner:
Next action:
```

## 8. Daily alpha smoke routine

- [ ] Check pending moderation count and oldest pending upload
- [ ] Review latest approvals/rejections
- [ ] Run one browse → detail → download smoke
- [ ] Run one upload → My Uploads smoke
- [ ] Run one RSS feed smoke
- [ ] Review security/audit/log surface if present
- [ ] Review feedback/issues from previous day
- [ ] Record blockers immediately

## 9. Handoff

Slice 109 should prepare alpha feedback/intake.
Slice 110 should handle deployment/runtime readiness.
Slice 111 should assemble the controlled alpha launch package.
Keep failures from this harness issue-scoped and assigned before launch.
Do not expand product scope from this document alone.
