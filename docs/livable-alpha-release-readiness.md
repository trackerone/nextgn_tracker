# Slice 106 – Livable Alpha Release Readiness Audit

Date: 2026-06-20
Repository: `trackerone/nextgn_tracker`
Scope: release-readiness decision support only. This slice intentionally does **not** add product features, routes, controllers, services, migrations, CI changes, or frontend polish.

## A. Executive summary

NextGN is **close enough for a controlled, invite-only Livable Alpha**, provided the launch is supervised by staff and treated as a limited validation window rather than a public opening.

Short answer: **yes, this can be opened to a controlled alpha group after a small pre-launch verification/fix pass.** The current product surface supports the core tracker loop: an authenticated member can orient from the dashboard, browse torrents, inspect a torrent detail page, attempt download/magnet access, upload a torrent into moderation, track their own uploads, and staff can moderate pending uploads.

There are **no confirmed launch blockers in the current audited product surface** from the documentation and route/test review in this slice. The remaining risks are mostly pre-launch must-fix or operational verification items:

- Run the manual smoke checklist against the real alpha environment and representative roles.
- Confirm demo/alpha data is representative enough to exercise member, uploader, staff, and sysop journeys.
- Confirm account/client setup guidance is sufficient for invited users who are not already familiar with private tracker workflows.
- Confirm RSS token/feed and download eligibility behavior with real configured secrets and storage.

If any of those checks fail in the alpha environment, the failed check should become the first item in **Slice 107 – Alpha Blocker Fix Pass**.

## B. Current green foundation

These areas are considered alpha-ready enough for a small controlled group:

- **Core browse/detail path:** authenticated torrent browse and detail routes exist, with search/filter throttling, compact listing/detail orientation, and download/magnet entry points.
- **Upload entry:** members can reach the upload form, submit a torrent, and receive validation/moderation-oriented guidance.
- **Dashboard orientation:** `/home` gives members a practical starting point for browse, upload, my uploads, discovery/watch, messages, ratio, and staff links where relevant.
- **My uploads:** uploaders can inspect pending/rejected/approved state and see rejection recovery copy.
- **Download and blocked download state:** web/API download paths are covered by eligibility and file-availability behavior, with blocked-state recovery copy improved in earlier slices.
- **RSS setup/feed path:** account RSS token, preset management, public tokenized feed routes, and RSS torrent download route exist.
- **Watch/follow/notifications:** follows, watch presets, notification list, and watch center are present enough to validate saved-intent reuse without expanding discovery scope.
- **Staff moderation/admin surface:** staff can review pending uploads, approve, reject with reasons, soft-delete, and inspect recent moderation history; sysop/admin surfaces expose operations, logs, invites, metadata, ratio, and role controls.
- **Mobile/readability pass:** Slice 105 applied a narrow readability and touch-target pass across dashboard, browse, detail, upload, My Uploads, and staff moderation.
- **Empty states and recovery copy:** key user-facing surfaces now avoid blank pages and provide more actionable no-results, missing metadata, upload, and blocked-download copy.
- **Existing docs foundation:** RSS, upload workflow, download flow, security, production operations, invite/signup, notification/watch presets, and the Slice 101 surface audit already describe major operational and product expectations.

## C. Launch blockers

A launch blocker is limited to issues where alpha users cannot complete a core flow, staff cannot operate/moderate, private tracker access is unsafe, CI exposes a critical unresolved regression, or data integrity risk is high.

Current blocker assessment:

| Blocker | Status | Notes |
| --- | --- | --- |
| Core member browse/detail flow unavailable | Not currently identified | Authenticated browse/detail routes and UI surfaces exist. Verify with smoke test. |
| Download/magnet access unsafe or unusable | Not currently identified | Authenticated download/magnet routes, RSS tokenized download, ratio/download eligibility, and tests exist. Verify storage and secrets in alpha. |
| Upload flow unusable | Not currently identified | Upload entry, validation, moderation submission, and My Uploads status exist. Preflight polish remains useful but is not a blocker for trusted uploaders. |
| Staff cannot moderate uploads | Not currently identified | Staff moderation queue/actions exist. Verify staff role and permissions in alpha data. |
| RSS token/feed behavior unsafe | Not currently identified | Tokenized routes and rotation exist. Verify token secrecy, HTTPS, and feed download eligibility in environment. |
| CI/test coverage exposes critical regression | Not currently identified | No product code changed in this slice. CI remains source of truth. |
| Representative alpha data absent | Conditional risk | If the launch environment lacks realistic torrents, users, roles, pending/rejected uploads, RSS/watch examples, and blocked-download fixtures, treat as a launch blocker for review confidence. |

## D. Pre-launch must-fix list

| Item | Why it matters | Suggested slice or task | Risk level |
| --- | --- | --- | --- |
| Run the full manual smoke checklist on staging/alpha with member, uploader, staff, and sysop accounts. | The codebase has the flows, but alpha readiness depends on configured roles, storage, secrets, and realistic data. | Slice 107 – Alpha Blocker Fix Pass or Slice 108 – Manual Smoke Test Harness / Launch Checklist. | High |
| Confirm representative alpha/demo data. | Staff and reviewers need visible approved torrents, pending/rejected uploads, empty states, RSS/watch examples, and blocked-download examples to validate real journeys. | Slice 107 task: seed/prepare alpha validation dataset without broad product expansion. | High |
| Verify first-use account/client setup copy. | Invitees may not know passkey, announce URL, RSS token, ratio, or client setup expectations. Missing guidance increases staff support load. | Slice 107 or 108: account/client setup verification and copy-only fixes if needed. | Medium |
| Verify RSS feed and RSS download behavior against real alpha configuration. | RSS is security-sensitive because tokenized feed/download links bypass normal UI session flow. | Slice 107 task: manual RSS token/feed/download verification. | Medium |
| Verify upload validation and moderation recovery using real `.torrent` fixtures. | Trusted uploaders must understand why a submission fails or is rejected and staff must see actionable metadata. | Slice 107 task: fixture-based manual upload/moderation smoke. | Medium |
| Confirm mobile browse/detail/upload paths on one phone-sized viewport. | Slice 105 improved readability, but actual launch confidence requires a human pass through the top mobile paths. | Slice 108 checklist item; only fix blocker-level issues. | Medium |
| Confirm production/security readiness commands and environment checks in CI/deploy pipeline. | Private tracker access depends on correct secrets, HTTPS, throttling, storage, and logs more than UI polish. | Slice 110 – Production Deployment Readiness Notes. | High |
| Prepare support/feedback triage labels and staff daily review routine. | Controlled alpha should produce actionable fixes instead of scattered comments. | Slice 109 – Alpha Feedback & Issue Intake Surface. | Medium |

## E. Explicitly deferred work

The following work should **not** block Livable Alpha and should remain out of launch-fix slices unless a direct alpha blocker proves otherwise:

- Recommendation engine expansion.
- Advanced discovery expansion.
- AI moderation.
- Reputation system.
- Curator/archive identity.
- Request system expansion.
- Predictive quality scoring.
- Automation ecosystem.
- Advanced swarm intelligence.
- Broad visual redesign.
- Full public tracker feature parity.
- Badge/flag/column expansion that adds clutter rather than reducing user effort.

## F. Alpha operating model

Run Livable Alpha as a **controlled, invite-only validation group**.

Recommended operating model:

- **Audience:** small trusted cohort of ordinary members, trusted uploaders, staff moderators, and one or more sysop/admin operators.
- **Access:** invite-only. Do not open public registration unless registration, abuse handling, email delivery, throttles, and support expectations are separately verified.
- **Staff moderation expectations:** staff should review pending uploads at least daily, reject unclear/invalid uploads with actionable reasons, approve only understandable releases, and coordinate edge cases outside the product if necessary.
- **Daily staff checks:**
  - Pending upload count and age.
  - Recent rejected uploads and whether reasons are understandable.
  - Browse/detail/download smoke path.
  - RSS feed availability for at least one account.
  - Watch/follow notification sanity.
  - Security/audit logs for suspicious auth, RSS, download, upload, and moderation activity.
  - Operations/health dashboard for storage, queue, mail, logs, and runtime warnings.
- **Feedback to collect:**
  - Could the user find a torrent without staff help?
  - Could the user understand whether they were allowed to download?
  - Could the user configure RSS or understand why they should not?
  - Could an uploader submit and recover from validation/rejection?
  - Could staff process moderation quickly and safely?
  - Which copy or state caused confusion?
  - Which failure forced out-of-band support?
- **Healthy alpha signals:**
  - Users can complete browse → detail → download or clear blocked-state understanding.
  - Uploaders can submit clean torrents and understand moderation results.
  - Staff can keep pending moderation low without manual database work.
  - No token, passkey, authorization, or visibility leaks are observed.
  - Feedback clusters into small copy/workflow fixes rather than missing-core-flow failures.
- **Unhealthy alpha signals:**
  - Multiple users cannot complete first download setup.
  - Staff cannot distinguish safe uploads from unsafe ones.
  - RSS/download links bypass intended eligibility or expose private data.
  - Product issues require direct database edits or code hotfixes to keep alpha running.

## G. Smoke test checklist

Use this checklist manually against staging/alpha before invitations are sent and again after deployment.

### Auth and session

- [ ] Register a new account if registration is in launch scope, or confirm invite-only registration path works.
- [ ] Log in as ordinary member.
- [ ] Log out and confirm authenticated pages redirect to login.
- [ ] Confirm stale/back-button session behavior does not expose private torrent pages after logout.

### Member browse/detail/download

- [ ] Open dashboard/home and confirm the next actions are understandable.
- [ ] Browse torrents.
- [ ] Search by text.
- [ ] Apply at least one filter.
- [ ] Clear or recover from a no-results state.
- [ ] Open torrent detail.
- [ ] Confirm metadata, description/NFO, stats, and action areas are readable.
- [ ] Download an allowed torrent.
- [ ] Open magnet link for an allowed torrent.
- [ ] Confirm blocked download state for a user who fails eligibility or lacks access.
- [ ] Confirm a missing torrent file/storage case does not leak unsafe information.

### Upload and My Uploads

- [ ] Open upload form.
- [ ] Submit invalid upload and confirm validation is understandable.
- [ ] Submit valid `.torrent` upload.
- [ ] View the submitted upload in My Uploads.
- [ ] Confirm pending status is clear.
- [ ] Confirm rejected upload shows actionable reason.
- [ ] Confirm approved upload becomes visible where expected.

### Staff and admin operations

- [ ] Log in as staff.
- [ ] Open staff moderation queue.
- [ ] Approve a pending upload.
- [ ] Reject a pending upload with reason.
- [ ] Soft-delete only where appropriate.
- [ ] Confirm unauthorized member cannot access staff moderation actions.
- [ ] Open admin/staff operations surfaces available to the role.
- [ ] Check audit/security logs for auth, upload, download/RSS, and moderation events where available.
- [ ] Confirm sysop operations/health view is readable for operators.

### RSS, watch, notifications, and saved intent

- [ ] Open account RSS setup.
- [ ] Rotate RSS token only in a test account and confirm old token behavior is understood.
- [ ] Open raw RSS feed with current token.
- [ ] Open preset RSS feed if presets are configured.
- [ ] Download via RSS feed link for an eligible torrent.
- [ ] Confirm RSS download is blocked or fails safely for ineligible access.
- [ ] Create or apply a saved view if present in the alpha path.
- [ ] Create watch preset if present.
- [ ] Follow/watch a torrent if present.
- [ ] Confirm notification/watch center path is understandable.

### Mobile/readability and empty states

- [ ] Repeat browse on phone-sized viewport.
- [ ] Repeat torrent detail on phone-sized viewport.
- [ ] Repeat upload form on phone-sized viewport.
- [ ] Confirm primary actions wrap without disappearing.
- [ ] Confirm table overflow or card summaries remain usable.
- [ ] Visit empty dashboard/browse/My Uploads/moderation states and confirm recovery copy tells the user what to do next.

## H. Recommended next slices

1. **Slice 107 – Alpha Blocker Fix Pass**
   Run the smoke checklist on staging/alpha and fix only issues that block core member, uploader, staff, security, or data-integrity flows.

2. **Slice 108 – Manual Smoke Test Harness / Launch Checklist**
   Turn the checklist into a repeatable release artifact with roles, fixtures, expected outcomes, and explicit pass/fail recording. Avoid broad automated test invention unless the repo already has matching docs/link checks.

3. **Slice 109 – Alpha Feedback & Issue Intake Surface**
   Prepare a lightweight issue intake and triage routine for alpha feedback, focused on reproducible flow failures, confusing copy, and staff support load.

4. **Slice 110 – Production Deployment Readiness Notes**
   Verify deploy/runtime requirements: secrets, HTTPS, storage disks, mail, queues, logs, backups, health checks, throttles, and rollback expectations.

5. **Slice 111 – Controlled Alpha Launch Package**
   Assemble invite language, staff daily checklist, known limitations, deferred-work list, smoke-test signoff, and go/no-go criteria.

## Slice 107 update – Alpha Blocker Fix Pass

Date: 2026-06-20

### What was inspected

Slice 107 re-checked the Slice 106 blocker boundary against the current alpha-critical code and tests for:

- Member dashboard, browse, search/filter, torrent detail, download/magnet, blocked download, and logout/session protection paths.
- Uploader upload form, invalid upload validation, valid `.torrent` submission, My Uploads status, rejected-upload recovery copy, and approved upload visibility paths.
- Staff moderation queue, approve/reject/soft-delete actions, moderation reason handling, and ordinary-member authorization boundaries.
- Account RSS setup, token rotation, tokenized feed/download routes, RSS download eligibility, and RSS feed enclosure safety.
- Existing operations/security-adjacent auth, authorization, upload/download, RSS token, and staff/admin boundaries covered by the current app surfaces and tests.

### What was fixed

No product behavior blocker was confirmed. The only code change is focused smoke coverage for the auth/session checklist item: after logout, an authenticated torrent page must redirect to login rather than remain available through the prior session.

### Confirmed blockers remaining

No confirmed launch blockers remain from this repository-level pass. The conditional risks from Slice 106 still require environment validation because local code review cannot prove staging/alpha secrets, storage, HTTPS, role data, realistic torrents, pending/rejected uploads, RSS/watch examples, or operator routines.

### Items moving forward

- **Slice 108:** convert the manual smoke checklist into a repeatable launch checklist/harness with representative roles, fixtures, expected outcomes, and pass/fail recording.
- **Slice 109:** prepare lightweight alpha feedback and issue-intake triage for reproducible flow failures and confusing copy found during controlled alpha.
- **Slice 110:** verify production deployment readiness for secrets, HTTPS, storage disks, mail, queues, logs, backups, health checks, throttles, and rollback expectations.

Slice 107 intentionally avoids product expansion, new systems, new controllers/routes, broad UI polish, and speculative fixes.

## Slice 108 update – Manual Smoke Test Harness

Date: 2026-06-20

Slice 108 created [`docs/livable-alpha-smoke-test-harness.md`](livable-alpha-smoke-test-harness.md).

The smoke checklist is now the operational go/no-go artifact for controlled alpha.

Product code was not changed.

## Slice 109 update – Alpha Feedback & Issue Intake Surface

Date: 2026-06-20

Slice 109 adds a lightweight authenticated alpha issue intake surface so blockers, must-fix issues, non-blocking feedback, smoke-test failures, affected area, role/environment context, blocker status, and next triage status can be captured consistently.

Unresolved blockers found while running the smoke-test harness should be entered through this alpha feedback intake instead of being left only in chat or notes. Staff can review submitted alpha feedback, prioritize blocker and must-fix reports, and update the status as items move through review.

This update is intentionally limited to controlled alpha issue intake and does not create a public bug tracker, support ticket system, comments, voting, attachments, notifications, or broader community feedback platform.
