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
