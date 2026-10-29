> **Feature plan:** [Step 1](unified_removal_format_step1_solution_assessment.md) · [Step 2](unified_removal_format_step2_feature_description.md) · [Step 3](unified_removal_format_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/unified_removal_format`; [ledger](../effort_estimation_trial.md#cycle-v3unified_removal_format).
Extended estimates: enabled.

**Cycle estimate: 0.70**

## Entry — 2026-10-29 UTC

Step 3 explicitly approved; planning commit `5143ab6f`, branch `feature/unified-removal-format`. Scope v2/A, original baseline/R1/P2 retained; single exposed Codex rater. Step 3 whole-cycle 0.70 reaffirmed. Original S1–S7/v2 stage work: 0.10/0.15/0.15/0.10/0.15/0.10/0.15; non-additive, prior v1 values in plan. No old-format compatibility or migration; no dependencies/deployment/data cleanup. All stages pending at entry. No measured labor or accepted outcome.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Receipt contract

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes: strict common codec/specification, explicit authority profiles and repeated label blocks. No old-format reader. Signed profile binds exact request/signature; source hash remains optional for signed requests, required for local actions, matching the existing signed target contract without inventing hashes.
- Verification: `php tests/run.php RemovalReceiptTest` 2/2; malformed/duplicate fields, invalid dates, contradictory authority, duplicate label bindings and anonymous resolution covered. Whitespace checks pass.
- Notes: S1/v2 unchanged; no writer switched yet. No dependencies. Initial runner did not discover the new suite until explicitly registered; final run passed.

*— Codex · 2026-10-29 UTC*
