# Codex Controlled Execution (Autopilot v1.5)

This document describes the conservative **v1.5** execution backend for issue-driven Codex work.

## Intent

Autopilot v1 prepared issue and PR intake only. v1.5 adds a guarded execution path that can produce a **branch + draft PR** outcome when explicitly requested.

The implementation is intentionally narrow:
- disabled by default,
- allowlist-restricted,
- branch/PR-oriented only,
- no merge automation.

## Trigger model

Workflow: `.github/workflows/codex-issue-implementation.yml`

Execution is requested only when one of the following is true:
- issue comment contains `/codex execute`, or
- workflow is run manually with `run_execution=true`.

Intake-only mode remains available with `/codex implement`.

## Required guards (all must pass)

Execution runs only when all are true:
1. Explicit execution request received.
2. Repository variable `CODEX_EXECUTION_ENABLED` equals `true`.
3. Secret `CODEX_EXECUTION_KEY` exists and is non-empty.

If a request is made without these guards, the workflow posts an intake comment and does **not** run execution.

## Allowlist policy

Policy file: `.codex/policies/execution-allowlist.txt`

During execution, changed files are compared against this allowlist. Any changed file outside configured prefixes fails the job.

Current v1.5 allowlist is intentionally minimal:
- `.codex/runs/`

## Current execution outcome

When guards pass, the backend:
1. Creates/updates branch `codex/issue-<number>-execution`.
2. Writes a durable execution packet in `.codex/runs/issue-<number>-execution.md`.
3. Opens or updates a **draft** PR against the default branch.

This preserves existing CI and review gates.

## Rollback / disable

Immediate disable options:
- Set repository variable `CODEX_EXECUTION_ENABLED` to `false` (or remove it), and/or
- remove secret `CODEX_EXECUTION_KEY`.

Optional hard rollback:
- revert `.github/workflows/codex-issue-implementation.yml` and remove `.codex/policies/execution-allowlist.txt`.

## Remaining gap (intentional)

v1.5 does not yet perform direct code editing beyond packet generation.

Next safe slice can connect packet-driven Codex edits to a tightly scoped allowlist expansion with the same guard model and draft PR-only delivery.
