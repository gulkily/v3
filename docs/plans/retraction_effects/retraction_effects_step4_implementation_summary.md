> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, `v3/retraction_effects`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects).

## Entry

- Approved Step 3 v5; branch `feat/retraction-effects`; planning commit `43ed5860` verified at HEAD before this summary or implementation.
- Scope: labels only, both removal paths, historical repair, durable removal and preserved replies. Remaining effect families and parent all-effects request stay incomplete.
- 2026-10-23 UTC, Codex single exposed rater, R1/P2, original baseline `e960f666e1146d26b2f9435d98a447c2594b9883`. Step 3 numeric projection explicitly remains unavailable pending exact frozen report/diff recovery. No retrospective or measured effort; no implementation or deployment verification yet.

## Stage 1 — Source-owned label provenance

- Changes: generated hashtag label records now carry source post ID and SHA-256 in thread/reply/signed publication paths. Parser retains legacy compatibility and requires a valid complete provenance pair. Shared ownership inspector checks source content, thread and author; absent/malformed/changed sources and legacy records remain unresolved. Independent reaction-label records keep their existing signed bytes. Thread-attributes prints ownership status/evidence.
- Verification: `php tests/run.php CanonicalRecordParsersTest` 46 passed; two focused WriteApiSmokeTest hashtag thread/reply publication tests passed with actual source-binding assertions. Writer PHP syntax passed. Initial new malformed-source test exposed a parser exception; inspector now reports invalid sources as unresolved and rerun passed. No UI/deployment claim.
- Notes: legacy inference deliberately does not use same author/time or bulk-import co-commit. Removal/evidence and later stages remain pending.
