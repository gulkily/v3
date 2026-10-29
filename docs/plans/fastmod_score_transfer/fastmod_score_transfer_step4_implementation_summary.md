> **Feature plan:** [Step 1](./fastmod_score_transfer_step1_solution_assessment.md) · [Step 2](./fastmod_score_transfer_step2_feature_description.md) · [Step 3](./fastmod_score_transfer_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_score_transfer; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
**Cycle estimate: 0.75**
Status: Step 3 approved; implementation in progress.

## Entry checkpoint

2026-10-29 UTC. User approved Step 3 after explicitly removing inclusion controls. Scope v2/A; same baseline 53f0c89260c08e3fdf1d5535380d84d7cec92768, R1/P2, single exposed Codex rater and inherited reference verification. Projection 0.75 reaffirmed; original stage-work S1–S8: 0.10/0.10/0.18/0.10/0.12/0.14/0.12/0.06, non-additive. No measured labor or maintainer acceptance. Planning commit 157df3f3 on feature/fastmod-score-transfer. No dependencies approved or needed; no deployment/merge authorization.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Define the scores supplement

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Bounded deterministic Key: value codec and versioned format specification. Unknown fields/versions and malformed values reject the entire supplement; exporter selects only approved fields.
- Verification: FastmodScoreTransferTest 2/2 pass, including round-trip, empty input, forbidden fields, invalid dates/probabilities and oversized input. Initial strict array-order assertion corrected to compare decoded field values; no behavior changed for it.
- Notes: No dependencies/schema changes; runtime integration belongs to later stages. Estimates unchanged, same scope/rater/date as entry.

*— Codex · 2026-10-29 UTC*

## Stage 2 - Preserve destination scores

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Transactional insert-only import with exact content/revision/named-rubric matching, preview, duplicate/existing/incompatible counts and imported origin. Added scoring concept.
- Verification: Transfer/store tests 14/14 pass; preview writes nothing, failure/local rows win, original ages persist, repeated import preserves rows, injected SQLite failure rolls back all inserts and creates no jobs.
- Notes: S1 commit a3efe835. No schema/dependency changes; original stage scope/estimate unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Export through existing downloads

**Original estimate: 0.18**
**Retrospective stage-work assessment: 0.18**

- Changes: Both public content formats automatically inject the bounded score snapshot; configured private score path is resolved through existing config. Content cache identity includes score bytes, including committed WAL updates; image identity does not. Reserved file content is replaced; no private database created on read.
- Verification: Archive/export regressions plus codec/store 15/15 pass; expanded transfer tests 5/5 pass with real TAR/ZIP parity, reserved file replacement, missing/empty store, score-only WAL updates, pruning, unchanged image cache. Existing compression-race/failure cleanup tests pass. Corrected codec numeric grammar for scientific notation beginning with digits other than 1.
- Notes: S2 commit 59fd0988. No setting/flag/dependency/schema introduced. Consistent bounded query snapshot; content hash matching reads only scored committed posts. No browser changes yet.

*— Codex · 2026-10-29 UTC*
