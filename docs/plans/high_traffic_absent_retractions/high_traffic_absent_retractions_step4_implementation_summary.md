> **Feature plan:** [Step 1](./high_traffic_absent_retractions_step1_solution_assessment.md) · [Step 2](./high_traffic_absent_retractions_step2_feature_description.md) · [Step 3](./high_traffic_absent_retractions_step3_development_plan.md) · [Step 4](./high_traffic_absent_retractions_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort`, cycle `v3/high_traffic_absent_retractions`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_absent_retractions), R1/P2.

## Entry

Step 3 approved. Branch `fix/high-traffic-absent-retractions`, planning commit `bf659506f`, original baseline `bce2c29e3`. **0.75 total reaffirmed** before implementation; scope v1, Codex single exposed rater, prior exposure and 0.65 snapshots retained. Extended estimates not enabled. Five stages pending. No dependencies, schema changes, deployment or production re-enable authorized. Production remains on ordinary PHP recovery. Unrelated README changes and standalone investigation remain outside stage commits.

## Stage 1 - Scoped evidence admission

- Changes: HTML-only context reuses safe absent-target classification and pins evidence, repository head and canonical-content fingerprint. Reappearance, uncommitted edits and unrelated changes invalidate HTML candidates; strict publication/snapshot semantics remain unchanged.
- Verification: `php tests/run.php HighTrafficPublicationTest AbsentRetractionSnapshotTest` passed 14/14; production IDs, malformed/unsafe evidence, changed inputs and unchanged strict rejection covered. No dependency/schema change.
- Notes: projection remains 0.75. New context is not wired into cache until render isolation is verified. Stage 2 will use a fresh disposable derivation only for absent-target rendering, avoiding stale live rows and preserving the canonical source.
