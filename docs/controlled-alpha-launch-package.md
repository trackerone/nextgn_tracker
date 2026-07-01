# Controlled Alpha Launch Package

This package is the post-SnapDeploy launch alignment for a controlled NextGN alpha. It is an operations and documentation artifact only; it does not add product features, deployment automation, installer code, or a new hosting platform.

## Purpose

Use this package to coordinate an invite-only controlled alpha, not a public launch. The goal is to validate that the existing tracker, metadata, discovery, upload, RSS, moderation, and operations surfaces are livable for a small trusted group before broader rollout.

Do not expand product scope during launch validation. Record missing features and polish requests as deferred work unless they block the smoke harness or prevent staff from safely operating the alpha.

## Deployment posture

SnapDeploy was removed after Slice 112 and is not an active deployment path. Do not reintroduce SnapDeploy, the removed pre-alpha bootstrap flow, or a replacement platform-specific launcher as part of alpha validation.

Current launch validation should use the normal Docker/runtime flow described by the production operations and alpha Docker readiness docs, or an already prepared staging/alpha environment that follows the same runtime expectations. Future installer direction remains a VPS/local installer path, not a new platform dependency for this slice.

## Required environment

Run launch validation in one of these environments:

- A staging/alpha environment with production-like secrets, HTTPS, persistent storage, queues, mail/log handling, backups, and rollback expectations reviewed.
- A production alpha candidate environment only after the same runtime, data, and operations checks have passed.

The environment must be stable enough for repeatable smoke testing, staff triage, feedback intake, and daily operations review. Local-only development data is not sufficient for launch signoff.

## Required roles

Prepare accounts for every launch-critical role before smoke testing:

- **Guest:** unauthenticated visitor for access boundaries, redirects, and public entry checks.
- **Member:** ordinary authenticated user for browse, detail, download/magnet, RSS, profile/session, and feedback journeys.
- **Uploader:** user allowed to submit torrents and review their upload states.
- **Staff:** moderation-capable user for queue review, approvals, rejections, staff-only surfaces, and alpha feedback triage.
- **Sysop:** highest-privilege operator for production checks, configuration review, emergency rollback coordination, and final go/no-go signoff.

## Required validation data

Seed or prepare representative alpha validation data without changing product behavior:

- Browseable approved torrents with realistic metadata, release groups, years, categories, languages, resolutions, sources, origins, and swarm states.
- Pending and rejected upload examples for uploader recovery and staff moderation flows.
- Download-eligible and download-denied examples for member and boundary checks.
- RSS/watch-preset examples where those surfaces are enabled.
- Feedback/intake records or a clean intake queue ready for smoke failures and alpha reports.
- Operational signals needed by staff, such as moderation counts, recent approvals/rejections, logs, queue status, backup status, and health-check output where available.

Use existing staging demo seeding guidance where appropriate, but keep any real alpha data deliberately limited, reviewable, and non-public.

## Required smoke harness

The controlled alpha go/no-go checklist is the [Livable Alpha Smoke Test Harness](livable-alpha-smoke-test-harness.md). Run it against the selected staging/alpha or production alpha candidate environment and record failures through the alpha feedback intake flow.

## Go/no-go rules

Alpha is **go** only when all of the following are true:

- No unresolved smoke-harness blocker remains.
- Must-fix issues are either resolved or explicitly accepted by staff/sysop with a documented mitigation.
- Required roles can complete their launch-critical journeys.
- Required validation data exists and is safe to expose to invite-only alpha users.
- Runtime readiness is confirmed for HTTPS, secrets, storage, queues, mail/log handling, backups, health checks, and rollback.
- Staff can review feedback, moderation, and operational status daily.

Alpha is **no-go** when any of the following are true:

- A blocker prevents login/session safety, browse/detail access, download eligibility, upload submission, moderation, RSS/feed safety, feedback intake, or staff/sysop operation.
- The environment does not match the required staging/alpha or production alpha candidate posture.
- Validation data is missing, misleading, unsafe, or too thin to exercise the smoke harness.
- Staff cannot receive, triage, or act on alpha feedback.
- The proposed fix requires product-scope expansion instead of a narrow blocker resolution.

## Feedback intake flow

1. Record smoke-test failures and invite-only alpha reports in the alpha feedback intake surface.
2. Use the same severity language as the smoke harness: `blocker`, `must-fix`, or `non-blocking`.
3. Mark whether the report blocks alpha, assign an owner, and add the next action.
4. Staff triages blocker and must-fix reports daily before accepting new scope.
5. Non-blocking feedback is grouped into deferred work unless it becomes a repeated support burden or reveals a safety issue.

## Daily staff routine

During controlled alpha, staff should complete this routine once per day:

- Review new alpha feedback and update severity, owner, status, and next action.
- Check pending moderation count and oldest pending upload.
- Review the latest approvals, rejections, and any uploader recovery reports.
- Run one browse → detail → download smoke path.
- Run one upload → My Uploads smoke path.
- Run one RSS/feed smoke path when RSS is enabled for the environment.
- Review logs, queues, health checks, backups, and operational alerts available for the environment.
- Escalate unresolved blockers to staff/sysop signoff before additional invites are sent.

## Known deferred work

These items are not launch blockers unless the smoke harness or staff/sysop signoff classifies a concrete failure as blocking alpha:

- Public launch growth, marketing, open registration, and broad invite automation.
- Recommendation, reputation, curator, request, and broader discovery-roadmap expansion.
- New installer code, deployment automation, or platform-specific launchers.
- Full public support desk features, voting, attachments, comments, or community feedback workflows.
- UI polish that does not prevent controlled alpha users or staff from completing launch-critical journeys.
