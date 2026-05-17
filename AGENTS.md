# NextGN Codex Agent Guide

This file defines default operating rules for Codex agents in this repository.

## NextGN working rules
- Prefer the smallest correct diff
- Never weaken lint, tests, or CI gates
- Verify repository conventions from existing code before introducing new patterns
- Avoid unrelated cleanup in feature or fix branches
- Add or update tests when behavior changes
- Keep PRs narrow and reviewable
- Preserve existing architecture unless the task explicitly requires structural work
- When unsure, choose the safest conservative implementation and explain the tradeoff

## Definition of done
- Code change is complete
- Relevant tests pass
- Linting remains green
- Summary explains what changed, risks, and next step

## Non-negotiable torrent metadata rules
- All metadata reads must go through `TorrentMetadataView`.
- No fallback logic outside `TorrentMetadataView`.
- No direct use of legacy metadata fields in views.

## Mission and scope
- Keep changes narrow, reviewable, and safe for semi-automation.
- Preserve existing CI quality gates (linting, static analysis, tests, builds).
- Do not add auto-merge or direct-to-main automation.
- Prefer workflows that prepare structured artifacts/comments over workflows that push code without review.

## Safe execution defaults
- Assume smallest possible scope first.
- Avoid broad refactors unless required to satisfy the requested task.
- Reuse existing project commands before introducing new tooling.
- Keep commits atomic where possible.

## Stack and conventions to verify before changes
- Backend uses Laravel + PHP 8.4 with PSR-12 style.
- Frontend uses Vite + Tailwind + React packages.
- Use repository services/actions/policies patterns already present under `app/`.
- For database access, use Laravel database abstractions and prepared statements.

## Required checks before proposing merge
Run relevant existing checks for touched areas:
- `composer lint`
- `composer analyse`
- `composer test`
- `npm run build` (for frontend or asset changes)

If checks cannot be run locally, explicitly state what remains unverified.

## Automation guardrails (Autopilot v1)
- Issue-driven automation must stay issue-scoped and explicit.
- PR automation should assist review with summaries/checklists, not bypass reviewers.
- Nightly automation can run maintenance checks and open/report findings, but must not auto-merge.
- Any write automation should target dedicated branches and pull requests only.
