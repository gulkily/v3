> **Feature plan:** [Step 1](./absent_retraction_snapshot_step1_solution_assessment.md) · [Step 2](./absent_retraction_snapshot_step2_feature_description.md) · [Step 3](./absent_retraction_snapshot_step3_development_plan.md) · [Step 4](./absent_retraction_snapshot_step4_implementation_summary.md)

Debug mode: enabled (inherited).
Effort study: `railspike-effort`, cycle `v3/absent_retraction_import`; [host ledger](../effort_estimation_trial.md#cycle-v3absent_retraction_import), R1/P2.

## Entry

Step 3 approved. Planning commit `fd4fa3839`; branch `feat/absent-retraction-snapshot`. Scope v3, original baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`; 0.70 projected total reaffirmed before implementation. Codex single exposed rater; prior snapshots preserved. No dependency changes, live repair/retry or deployment authorized. Five stages pending.

## Stage 1 - Bound snapshot validation

- Changes: snapshot-only evidence scan distinguishes confirmed absent IDs from effective retractions; strict callers remain strict. Snapshot generation binds evidence bytes and excludes only initially absent targets through reappearance; malformed or removed evidence still invalidates publication.
- Verification: `php tests/run.php AbsentRetractionSnapshotTest RetractionSweepTest ReadModelPostRetractionsTest` passed 27/27; PHP syntax checks passed. Tests cover flat/dated absence, reappearance with unrelated author, changed evidence, malformed/symlink targets and missing tracked retraction. Initial new fixtures were corrected to follow canonical Record-ID/body format before passing.
- Notes: Stage 1 resolves build-local isolation contract; no caller uses the new context until Stage 2. Planning commit fd4fa3839. Runtime reader/CLI acceptance remains pending.

## Stage 2 - Publish filtered base snapshots

- Changes: builder uses a snapshot-only guard token and filters initially absent posts before expansion validation. An excluded root retains content-free thread metadata and surviving replies; thread last activity/counts use surviving posts. Source/canonical records stay unchanged. Generation is bound to the original scan to detect intervening unrelated evidence changes.
- Verification: focused absent/base/publisher/builder checks passed 10/10; stale secret bytes absent, source database hash unchanged, root subject/preview cleared and surviving reply retained. Syntax checks passed.
- Notes: Stage 1 commit 622adaf59. No core read-model query/schema changes; temporary filtering is applied after unchanged queries. Portable update exclusion follows in Stage 3.

## Stage 3 - Remove stale base copies through updates

- Changes: portable snapshot version 5 carries `excluded_posts`; actual reader applies exclusions outside included threads, clears excluded-root presentation and recomputes affected counts/activity without deleting surviving replies. Legacy snapshots without exclusion metadata remain supported. Runbook documents the artifact contract and required reader refresh.
- Verification: 18/18 focused absent/builder/manifest/reader-presentation checks passed, including real SQL.js execution of generated base/update artifacts, repeated update application, excluded replies/roots outside replaced threads and legacy artifacts. Node syntax and diff checks passed. No new dependency was added; existing SQL.js is reused.
- Notes: Stage 2 commit 5c7b6a3a0. Portable schema changed, not core read-model tables/queries. Browser-rendered acceptance remains Stage 5. Existing saved clients need the application reader refresh on reconnect.
