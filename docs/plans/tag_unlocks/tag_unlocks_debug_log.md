# tag_unlocks debug log

## Step 1 - 2026-11-11 UTC

### Necessary/useful instructions

- `step1_solution_assessment.md`, Original Query and Understood Intent: the second message narrowed the general request to meta status. Both messages are preserved, and the intent says which Pollyanna behavior applies. Retain.
- `RAILSPIKE.md`, extended estimates per option: made the cost gap between a general tagset file and hard-coded tags visible. Retain.
- `docs/reference/railspike_workflow.md` plus the `AGENTS.md` P2 enrollment: settled study enrollment without asking. Retain.

### Redundant/unnecessary instructions

- `RAILSPIKE.md`, frozen-reference recovery detail: the hashes matched on the first check, so the recovery procedure was read but not needed. Suggest: keep, but point to it only when a hash mismatches.

### Missing or unclear

- Prior art lived in a different repository (Pollyanna). Step 1 has no slot for cross-project prior art, so a Prior Art section was added, following `work_tracking`. Suggest: name an optional Prior Art section in the Step 1 structure.

## Step 1 Revision 1 - 2026-11-11 UTC

- Missing or unclear: the first draft guessed the unit (thread) without asking for a concrete example. The maintainer's example thread moved the scope to replies. Suggest: Step 1 should ask for one real example when the request names a category ("meta posts") rather than an instance.

## Step 2 - 2026-11-12 UTC

### Necessary/useful instructions

- `step2_feature_description.md`, Shared component inventory: inspecting `applyPostTag` found the duplicate-mark guard, which would break latest-wins. Surfaced as a risk and raised the estimate. Retain.
- `implementation_standards.md`, known user/environment: recorded iPhone verification. Retain.

### Missing or unclear

- None new.

## Step 3 - 2026-11-12 UTC

### Necessary/useful instructions

- `step3_development_plan_do.md`, resolve each material risk before dependent work: put the duplicate-guard test in Stage 2 ahead of UI stages. Retain.
- `step3_development_plan_do.md`, known environment replay: Stage 7 covers the iPhone check. Retain.

### Missing or unclear

- The one-page limit is hard to meet with seven stages in extended mode, since each stage needs six fields. Suggest: allow a stage-count-based exception or a compact field layout.

## Step 4 - 2026-11-12 UTC

### Necessary/useful instructions

- `step4_implementation_before.md`, planning commit gate: main had moved (schema 54 landed) and the study ledger conflicted on rebase; resolving it before the planning commit kept the ledger out of that commit as required. Retain.
- `step4_implementation_do.md`, stage boundary protocol: one commit per stage kept the Stage 2 intent-lookup regression contained and easy to explain. Retain.
- `implementation_standards.md`, check shared consumers: grepping themes for direct-child `.post-card >` selectors found the word97/midnight title-bar rules before they broke. Retain.

### Redundant/unnecessary instructions

- Extended-estimate retrospective per stage duplicates much of the cycle retrospective for small stages. Suggest: allow "as estimated" for stages within 0.02 of the original.

### Missing or unclear

- No instruction covers running browser checks (`tests/browser/*.mjs`) or how to import Playwright in this workspace; it took two tries. Suggest: a one-line pointer in `docs/reference/railspike_workflow.md` or `CONTRIBUTING.md`.
- A piped test command (`... | tail -1 && git commit`) hid a failing browser run before the Stage 5 commit; the failure did not reproduce in seven later runs. Suggest: say in the stage protocol that verification exit codes must gate the commit.
