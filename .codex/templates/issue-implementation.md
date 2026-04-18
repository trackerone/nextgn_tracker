# Codex issue implementation packet

## Issue
- Number: {{issue_number}}
- Title: {{issue_title}}
- URL: {{issue_url}}

## Requested scope
{{requested_scope}}

## Constraints
- Keep diff narrow and reviewable.
- Preserve linting, static analysis, tests, and build gates.
- Do not modify unrelated product logic.
- No auto-merge.

## Execution checklist
- [ ] Confirm acceptance criteria from issue text.
- [ ] Identify minimum files that need change.
- [ ] Implement smallest complete solution.
- [ ] Run relevant checks (`composer lint`, `composer analyse`, `composer test`, `npm run build` when applicable).
- [ ] Prepare concise PR summary + risk notes.
