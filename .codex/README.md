# NextGN Autopilot v1

This folder contains durable guidance for repeatable Codex-assisted workflows.

## Purpose
Autopilot v1 provides conservative automation foundations:
- issue-scoped implementation intake
- PR review assistance intake
- optional nightly maintenance checks

## Design principles
- Keep humans in review/approval loop.
- Reuse existing CI commands.
- Produce explicit task briefs/checklists so Codex runs are reproducible.
- Do not auto-merge.

## Files
- `templates/issue-implementation.md` — standard issue execution packet.
- `templates/pr-review.md` — standard PR review packet.

## Operational notes
- Workflows in `.github/workflows/codex-*.yml` intentionally default to reporting and briefing.
- Maintain strict repository rules in `AGENTS.md`.

## Autopilot v1.5 controlled execution
- Execution remains disabled by default and requires explicit guard enablement.
- Guarded execution entrypoint is in `.github/workflows/codex-issue-implementation.yml`.
- Path policy lives in `.codex/policies/execution-allowlist.txt`.
- See `docs/automation/codex-controlled-execution.md` for enable/rollback and safety notes.
