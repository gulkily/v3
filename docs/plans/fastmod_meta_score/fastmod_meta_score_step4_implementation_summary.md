> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress on `feat/fastmod-meta-score`. Planning commit: `08b9b874`.

## Stage 1 - Modular rubric contract
- Changes: Added catalog/composer with deterministic stable identities, compact moderation/support templates, shared introduction, nonempty selection validation, and effective composition hashes. Configuration accepts a rubric list; production wiring follows in Stage 3.
- Verification: `php tests/run.php FastmodRubricsTest FastScoringConfigTest` — 6 passed; new PHP files lint clean. Tests cover omission, ordering, invalid sets, added templates, empty text, and revision changes. Compared moderation template against existing categories and exceptions.
- Notes: Both default rubrics are enabled when unset; sorting establishes the output order independent of operator click order. No core read-model change.

## Stage 2 - CSV evaluation and durable score sets
- Changes: FastPostScorer uses the existing text completion contract, parses complete CSV atomically, and produces a named score map. Private score storage migrates to nullable score-set JSON; legacy rows remain explicitly legacy moderation-only. Compatibility probability is moderation only, never an aggregate or first score.
- Verification: `php tests/run.php FastmodCsvTest SqliteFastScoreStoreTest FastScoreWorkflowServiceTest` — all passed. Added provider-call counting, malformed output rejection, meta-only result, prior-store migration, and restart/idempotence coverage.
- Notes: Private-store schema only; core read-model schema unchanged. Effective prompt/factory and worker wiring follows in Stage 3.

## Stage 3 - Worker and revision integration
- Changes: Factory composes enabled templates and uses text completion; enqueue/audit/backfill revision calculation now uses the same composition. Worker execution uses its captured scorer revision to reject stale work before calling the provider. Added shared persisted-selection reader for HTTP/CLI/worker; saves follow in Stage 4.
- Verification: Focused CSV/rubric/sweep/revision/audit/backfill/task-worker tests passed, including new stale-revision exclusion with zero provider calls. Existing budget/call accounting tests pass.
- Notes: Stale work is recorded as excluded with `rubric_changed_before_score`, with no implicit replacement enqueue. Explicit audit/backfill can request new-revision evaluation. Introduction defaults to the new shared text; explicit prompt paths remain supported as introduction overrides.
