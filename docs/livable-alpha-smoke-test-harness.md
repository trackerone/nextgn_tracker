# Livable Alpha Smoke Test Harness

Date: 2026-06-20
Scope: manual launch verification for controlled, invite-only Livable Alpha. This harness is documentation only; it does not introduce product features, routes, seeders, migrations, CI changes, or automated smoke-test infrastructure.

## A. Purpose

This document turns the Slice 106/107 release-readiness checklist into a repeatable manual verification harness for Livable Alpha. Use it to prove that invited members, trusted uploaders, staff moderators, and sysop/admin operators can complete the small set of flows required to run a supervised alpha safely.

The harness is intentionally operational and conservative:

- It defines who tests each flow and which account types are required.
- It identifies the minimum fixture/data set needed for meaningful validation.
- It records expected results, pass/fail status, notes, and whether a failure blocks alpha.
- It distinguishes launch-blocking safety/core-flow failures from non-blocking follow-up work.
- It gives staff a daily smoke routine for the controlled alpha period.

This document should be used alongside the release-readiness audit, surface audit, upload workflow, download flow, RSS feed, security checklist, and production operations runbooks. Do not use this harness as permission to expand product scope during launch verification.

## B. Test environments

| Environment | Purpose | Expected use |
| --- | --- | --- |
| Local/dev, if available | Fast preflight for docs, copy, role setup assumptions, and obvious route regressions. | Useful before staging, but never sufficient for go/no-go because local secrets, storage, queue, mail, HTTPS, and role data may differ from alpha. |
| Staging/alpha | Primary go/no-go environment. | Run the complete smoke matrix here using representative roles, configured storage, real alpha secrets, HTTPS, and realistic validation data. |
| Production alpha candidate | Final deployment candidate before invitations. | Run the alpha-blocking rows and production/security checks after deployment and before sending invites. Do not broaden testing into destructive feature exploration. |

Staging/alpha is the real go/no-go environment. Local success is helpful, but it cannot prove token safety, storage correctness, production secrets, role assignments, or moderation operations in the launch environment.

## C. Required roles/accounts

| Role/account | Required permissions | Must be able to access | Must not be able to access |
| --- | --- | --- | --- |
| Guest | No authenticated session. | Public/login routes and any intentionally public tokenized RSS URL only when a valid RSS token is supplied. | Private dashboard, browse, torrent detail, upload, My Uploads, staff moderation, sysop/admin operations, private torrent files, and tokenless downloads. |
| Ordinary member | Authenticated standard user with normal download eligibility for at least one fixture torrent. | Dashboard/home, browse, search/filter, torrent detail, allowed download/magnet where eligible, account RSS setup if enabled for members, watch/follow/preset paths if present. | Staff moderation actions, sysop/admin operations, other users' private tokens/presets, ineligible restricted downloads, pending/rejected uploads outside authorized visibility. |
| Uploader/member | Authenticated member allowed to submit torrents. | Everything an ordinary member can access, plus upload form, upload validation, valid `.torrent` submission, and My Uploads pending/rejected/approved states. | Staff approval/rejection actions, sysop/admin operations, direct database/state edits, bypass of upload validation or moderation. |
| Staff moderator | Authenticated staff user with moderation privileges. | Staff moderation queue, pending upload review, approve/reject with reason, soft-delete where existing, recent moderation history, staff-visible upload states needed for review. | Sysop-only operations if not separately granted, production secrets, shell/command execution through the app, unsafe bypasses of upload policy. |
| Sysop/admin | Authenticated operator/admin account with the appropriate elevated permissions. | Sysop/admin operation surfaces, logs/audit/security surfaces where present, health/operations views, role-appropriate settings and launch verification dashboards. | Raw secrets, plaintext passkeys/tokens, direct file paths beyond intentional operational visibility, destructive operations not already part of the product surface. |

Use separate accounts for each role whenever possible. If one account has multiple roles, record that explicitly because combined permissions can hide authorization failures.

## D. Required fixture/data set

Prepare the smallest representative data set that exercises launch-critical behavior. Do not add seeders or migrations for this slice unless an existing fixture pattern makes that change explicitly tiny and reviewable.

Minimum alpha validation data:

- Approved visible torrent that an ordinary member can browse, open, and download.
- Approved visible torrent with populated metadata such as category, language, audio/subtitle language, resolution, source, release group, and year where available.
- Approved visible torrent missing some metadata so missing/partial metadata copy can be verified.
- Freeleech or eligibility-friendly torrent if the environment supports freeleech/eligibility distinctions.
- Torrent that should be blocked by eligibility for a restricted user if the environment supports a restricted-user scenario.
- Pending upload visible to staff moderation and to the uploader in My Uploads.
- Rejected upload with an actionable rejection reason visible to the uploader.
- Approved upload that is visible where published torrents are expected to appear.
- User with a current RSS token.
- User without an RSS token, or a token-rotation case that proves old RSS feed/download URLs stop working.
- Watch/follow/preset example if those surfaces are present in the alpha environment.
- Staff account with upload moderation permissions.
- Sysop/admin account with launch-relevant operations/log/security visibility.

Record fixture IDs, usernames, and any token-rotation notes in a private launch worksheet, not in this repository, because tokens and passkeys are sensitive.

## E. Smoke test matrix

Use this matrix for the full staging/alpha pass. Mark each row pass/fail, add concise notes, and treat every `yes` row as alpha-blocking unless the launch lead explicitly scopes that row out before invitations.

| Area | Role | Test action | Expected result | Pass/fail | Notes | Blocks alpha? |
| --- | --- | --- | --- | --- | --- | --- |
| Auth/session | Guest | Open a private authenticated page such as dashboard/home or browse. | Guest is redirected to login and no private torrent/member data is shown. |  |  | yes |
| Auth/session | Ordinary member | Log in with valid member credentials. | Session starts successfully and the member reaches the expected authenticated landing page. |  |  | yes |
| Auth/session | Ordinary member | Log out. | Session ends cleanly and the user sees the expected logged-out/login state. |  |  | yes |
| Auth/session | Guest after logout | Use browser back button or direct URL for a previously private page after logout. | Private page redirects to login or otherwise denies access; stale private content is not usable. |  |  | yes |
| Member | Ordinary member | Open dashboard/home. | Dashboard loads and gives understandable next actions for browse, upload/account, RSS/watch, messages/ratio, and role-appropriate staff links. |  |  | yes |
| Member | Ordinary member | Browse torrents. | Approved visible torrents load without errors and private/pending/rejected content is not exposed. |  |  | yes |
| Member | Ordinary member | Search and apply at least one filter. | Results update predictably using visible/eligible torrent data. |  |  | yes |
| Member | Ordinary member | Trigger a no-results browse/search state and recover. | No-results state explains what happened and how to clear/change filters. |  |  | no |
| Member | Ordinary member | Open torrent detail for an approved visible torrent. | Detail page loads with readable metadata, description/NFO where present, stats, and action areas. |  |  | yes |
| Member | Ordinary member | Download an allowed torrent. | `.torrent` file downloads through the app and uses personalized announce behavior without exposing storage paths. |  |  | yes |
| Member | Ordinary member | Open magnet link if present. | Magnet action works or presents the expected supported-state behavior. |  |  | no |
| Member | Restricted/ordinary member | Attempt a download that should be blocked by eligibility. | User receives safe blocked/denied state; no private file, passkey, storage path, or restricted metadata leaks. |  |  | yes |
| Uploader | Uploader/member | Open upload form. | Form loads with required fields, guidance, and moderation expectations. |  |  | yes |
| Uploader | Uploader/member | Submit invalid upload data or a non-`.torrent` file. | Validation prevents submission and explains the issue without creating an unsafe torrent row. |  |  | yes |
| Uploader | Uploader/member | Submit a valid `.torrent` fixture. | Upload is accepted into moderation as pending, not published immediately for ordinary visibility. |  |  | yes |
| Uploader | Uploader/member | Open My Uploads for the submitted torrent. | Pending status is visible and understandable. |  |  | yes |
| Uploader | Uploader/member | Inspect a rejected upload with a reason. | Rejection reason is visible and actionable to the uploader. |  |  | yes |
| Uploader | Uploader/member | Inspect an approved upload. | Approved upload appears where published/visible torrents are expected to appear. |  |  | yes |
| Staff | Staff moderator | Open moderation queue. | Pending uploads load with enough information to review safely. |  |  | yes |
| Staff | Staff moderator | Approve a pending upload. | Pending upload transitions to published/approved through the existing moderation flow and becomes visible as expected. |  |  | yes |
| Staff | Staff moderator | Reject a pending upload with a reason. | Upload transitions to rejected and the uploader can see the reason. |  |  | yes |
| Staff | Staff moderator | Soft-delete where the existing moderation surface supports it. | Soft-deleted torrent is removed from ordinary visibility and state is clear to staff. |  |  | no |
| Staff | Ordinary member | Attempt to access staff moderation pages/actions. | Member is forbidden or redirected; moderation data/actions are not exposed. |  |  | yes |
| RSS/watch | Ordinary member with RSS token | Open account RSS setup page. | Page loads, explains token/feed basics, and shows current token/feed controls safely. |  |  | yes |
| RSS/watch | Tokenized RSS client/browser | Open raw tokenized RSS feed with the current token. | Feed returns eligible visible torrents only; invalid/missing token returns safe not-found/denied behavior. |  |  | yes |
| RSS/watch | Ordinary member with RSS token | Rotate RSS token in a test account. | New feed/download URLs work and old feed/download URLs stop resolving. |  |  | yes |
| RSS/watch | Tokenized RSS client/browser | Download a torrent through an RSS enclosure/download link. | Download uses the RSS token route, re-checks eligibility, and serves the same safe personalized `.torrent` behavior. |  |  | yes |
| RSS/watch | Restricted/ordinary member | Attempt ineligible RSS feed/download access where supported. | Feed excludes ineligible items or download fails safely without leaking private access. |  |  | yes |
| RSS/watch | Ordinary member | Create/use watch, follow, saved view, or RSS preset example if present. | Saved intent path is understandable and does not expose other users' presets/tokens. |  |  | no |
| Operations/security | Sysop/admin | Open sysop/admin operation surface. | Role-appropriate operations page loads and is readable for launch checks. |  |  | yes |
| Operations/security | Staff or sysop/admin | Open logs/audit/security surface if present. | Launch-relevant auth, upload, download/RSS, moderation, and security events can be reviewed without exposing secrets. |  |  | yes |
| Operations/security | Ordinary member or test fixture | Exercise storage/file missing case if already supported by fixture/environment. | Missing file fails safely with no storage path, token, passkey, or private data leak. |  |  | yes |
| Operations/security | Guest | Attempt private torrent detail/download access. | Guest cannot access private torrent page or file and is redirected/denied safely. |  |  | yes |
| Mobile/readability | Ordinary member | Browse on phone-sized viewport. | Browse remains readable and primary actions/filters are usable without disappearing. |  |  | no |
| Mobile/readability | Ordinary member | Open torrent detail on phone-sized viewport. | Metadata, actions, and blocked/allowed download states remain readable. |  |  | no |
| Mobile/readability | Uploader/member | Open upload form on phone-sized viewport. | Required fields and submit/validation states remain usable. |  |  | no |
| Mobile/readability | Staff moderator | Open moderation queue on narrow viewport if relevant. | Staff can identify pending uploads and reach approve/reject actions, or desktop-only staff expectation is documented. |  |  | no |

## F. Go/no-go rules

Launch may proceed only if all of the following are true:

- Every alpha-blocking row in the smoke matrix passes in staging/alpha, and the final production alpha candidate has passed the alpha-blocking subset.
- Non-blocking issues are recorded with owner/next-slice notes instead of being lost in chat or ad hoc comments.
- Staff understands the daily moderation, RSS/download, logs/security, and feedback review routine.
- No private access, token, passkey, storage path, RSS, or download leak is found.
- Upload submission and staff moderation can operate without manual database edits.

Launch must stop if any of the following are true:

- A member cannot browse, open torrent detail, or download where eligible.
- Staff cannot review and moderate uploads.
- Uploads cannot be reviewed safely or require direct database edits to progress.
- RSS token/feed/download behavior leaks private access or bypasses intended eligibility.
- Auth/session/private pages are unsafe for guests or logged-out users.
- Alpha operation requires manual database edits to perform normal member, uploader, staff, or sysop/admin tasks.

## G. Failure recording template

Use this compact template for every failed or questionable row. Store records in the alpha issue tracker or launch worksheet; do not paste secrets, tokens, passkeys, or private file paths into public issues.

```text
Date/time:
Environment:
Role:
Area:
Steps to reproduce:
Expected result:
Actual result:
Screenshot/log link:
Severity:
Blocks alpha? yes/no:
Proposed next slice/task:
```

Severity guidance:

- **Blocker:** Stops launch under the go/no-go rules.
- **High:** Does not immediately leak private data or halt launch, but causes repeated staff intervention or user failure.
- **Medium:** Confusing or unreliable flow with a workaround.
- **Low:** Copy/readability/polish issue that can safely move to a later slice.

## H. Daily alpha smoke routine

During controlled alpha, staff should run this short routine at least daily and after each deployment:

- Check pending moderation count and oldest pending upload age.
- Review recent approvals/rejections and confirm rejection reasons are understandable.
- Smoke browse -> detail -> download with one ordinary member account.
- Smoke one RSS feed and one RSS torrent download using a test account with a current token.
- Smoke upload by submitting or validating a known fixture when safe to do so.
- Review logs/security/audit surfaces for suspicious auth, upload, download/RSS, moderation, queue, storage, or runtime warnings.
- Review feedback/issues, group duplicates, and assign each launch-relevant failure to a next slice or task.

If the daily routine finds a private access leak, unsafe token behavior, broken moderation, or eligible-download failure, pause invites and handle it as an alpha blocker.

## I. Slice handoff

This harness feeds the next launch slices as follows:

- **Slice 109 – Alpha Feedback & Issue Intake Surface:** use the failure template, severity guidance, and daily feedback review to define how alpha users and staff report reproducible flow failures, confusing copy, and support-load issues.
- **Slice 110 – Production Deployment Readiness Notes:** use the environment section, operations/security rows, and go/no-go rules to verify secrets, HTTPS, storage disks, queues, logs, backups, health checks, throttles, and rollback expectations.
- **Slice 111 – Controlled Alpha Launch Package:** use the required roles, fixture/data set, smoke matrix signoff, daily smoke routine, and go/no-go rules as the launch checklist included with invite/staff materials.
