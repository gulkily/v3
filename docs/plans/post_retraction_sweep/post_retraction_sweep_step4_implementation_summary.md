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

## Stage 3 - Late exchange writes
- Changes: all three production recorder factories supply canonical repository context; known affected results scrub before insertion. Status rechecks canonical generation and exchange high-water mark so racing/older writers expose pending work rather than stale completion.
- Verification: passed `php tests/run.php RetractionSweepTest LlmExchangeRecorderTest SqliteLlmExchangeStoreTest` (10 tests); includes late success/error payload policy and uncoordinated late insertion recovery. Existing exact-recording behavior remains for unaffected/uncertain records.
- Notes: canonical uncertainty preserves recording and withholding; current status fails closed. SQLite write transactions serialize sweep updates against inserts; later inserts invalidate observed completion. Stage 2 commit: 6aa88468.

## Stage 4 - Artifact cleanup and publication safety
- Changes: resumable bounded artifact traversal covers legacy files, active/inactive releases, build candidates, offline copies/manifests and temporary payload files; fresh generation-stamped artifacts survive repeat runs. Publication generation checks reject stale render/snapshot/activation; canonical writes share a narrow publication lock with final publication and retirement.
- Verification: passed 16 focused sweep/offline tests; PlatformDocsStaticTest passed (1); ImportedContentPublicationTest passed (2) after fixing lock placement outside canonical tracked files. Resumption, stale publication, directory-symlink rejection and repeat-clean retention verified.
- Notes: render/provider work is outside publication lock; lock order is read-model write lock then publication lock. Stale models reject publication until ordinary refresh/rebuild. Legacy shared releases require one full rebuild to establish generation evidence. Stage 3 commit: 7ebd5166.

## Stage 5 - Automatic queue and cron recovery
- Changes: acceptance/retry enqueue callback, coalesced sweep task and bounded continuation; existing worker discovers missing/imported work and reconciles completed coverage every five minutes. Private state defaults are instance/configuration-specific. Explicit enqueue/manual run retries terminal failures.
- Verification: passed 31 focused sweep/queue-command/worker tests. An isolated fixture executes the actual generated cron command and verifies missed-enqueue discovery, two payload scrubs and completed queue status. Coalescing, bounded continuation and terminal failure visibility tested. Empty-worker test now names an isolated no-retraction repository rather than discovering real local retractions.
- Notes: queue failure cannot undo acceptance; failed sweep status blocks automatic rediscovery to avoid endless terminal retries. Existing queued attempts still retry normally. Stage 4 commit: 98c3dd47.
