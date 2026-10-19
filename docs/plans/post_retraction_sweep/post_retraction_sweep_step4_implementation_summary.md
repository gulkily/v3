> **Feature plan:** [Step 1](./post_retraction_sweep_step1_solution_assessment.md) · [Step 2](./post_retraction_sweep_step2_feature_description.md) · [Step 3](./post_retraction_sweep_step3_development_plan.md) · [Step 4](./post_retraction_sweep_step4_implementation_summary.md)

Debug mode: enabled

Planning commit: 6c6683f2. Step 3 approved; seven sequential stages.

## Stage 1 - Operator preview and coverage
- Changes: shared validated preview, CLI entry and bounded exchange classification; explicit coverage/exclusions inventory.
- Verification: passed `php tests/run.php RetractionSweepTest` (1 test); mutation hash and absent-state assertions. Artifact enumeration is implemented and tested in Stage 4; preview currently reports store coverage and exchange counts.
- Notes: canonical validation reused fail-closed; no read-model schema change. Writer inventory found full/shared release builders, activation, direct builders, snapshot publishers, and three exchange-recorder factories. Existing private analysis/cache and read-model candidate/recovery stores excluded explicitly.

## Stage 2 - Immediate exchange cleanup
- Changes: manual bounded run, atomic private progress, restart cursor, allowlisted scrub markers; known affected payloads and diagnostic strings removed while uncertain/unaffected rows remain exact. Visibility always withholds scrubbed rows.
- Verification: passed `php tests/run.php RetractionSweepTest` (3 tests), including bounded continuation, repeated runs, raw database/WAL sentinel absence, and invalid evidence failure status.
- Notes: secure_delete is enabled for updates; WAL truncation must succeed before clean status. Run failures reset progress conservatively for retry. No read-model schema changes; private progress uses JSON. Stage 1 commit: 445203be.
