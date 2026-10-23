> **Feature plan:** [Step 1](./thread_like_activity_step1_solution_assessment.md) · [Step 2](./thread_like_activity_step2_feature_description.md) · [Step 3](./thread_like_activity_step3_development_plan.md) · [Step 4](./thread_like_activity_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, `v3/thread_like_activity`; [ledger](../effort_estimation_trial.md#cycle-v3thread_like_activity).

## Entry

- Step 3 approved; branch `feat/thread-like-activity`; planning commit `ab24d158` verified at HEAD before summary/application edits.
- Scope v1: every eligible canonical like contribution is reported once, independent of prior label presence. Different signing keys can have the same displayed username; the actual local incident uses two keys both currently named `ilyag`. No claim that the user previously clicked Like is required for the fix.
- 2026-10-23 UTC; baseline `39be82932fcc36c5017fcf368655ef449bf042da`; Codex single exposed rater; R1/P2. Step 3 total projection remains unavailable pending exact frozen-reference recovery, not zero or measured effort. Broader repository coverage, unlike controls and inactive-source duplicate-guard fixes remain separate.

## Stage 1 — completed

Full and incremental derivation now emit one event per eligible like-bearing record, retaining mixed-label grouping and existing score deduplication. Normal signed writes, duplicate retry, distinct same-author comments and rebuild parity passed; existing retraction coverage confirms withdrawn contributions stay excluded. Focused verification: 10 tests passed. No eligibility or vote calculation changed.

## Stage 2 — completed

Incremental refresh resolves each canonical label path's own commit instead of borrowing the triggering write's commit. Unavailable/invalid history is stored as null. Actor, original timestamp and canonical action keys are preserved. Five focused tests passed, including three distinct source commits compared against Git and full rebuild, one-row cursor traversal/count agreement, duplicate retry, and existing link contracts. Existing source/signature enrichment remains shared by consumers.

## Stage 3 — completed

Read-model version 24 forces normal startup to recover omitted like events from version-23 databases, without changing table shape or canonical records. The schema specification describes the derivation change. Both focused tests passed: a deliberately omitted second like was recovered before normal Activity rendering, `./v3 rebuild` retained both items without adding records, and existing incremental-failure/full-rebuild fallback remained healthy.
