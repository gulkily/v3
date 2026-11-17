# Import planning speed Step 4 implementation summary

> **Feature plan:** [Step 1](./import_planning_speed_step1_solution_assessment.md) · [Step 2](./import_planning_speed_step2_feature_description.md) · [Step 3](./import_planning_speed_step3_development_plan.md) · [Step 4](./import_planning_speed_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/import_planning_speed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55**

Branch: `feat/import_planning_speed`, from `origin/main` at `f9d61535`. Planning commit `4bc38c64`.

## Entry notes

- Step 4 entry projection: 0.55 reaffirmed from Step 3, 2026-11-17 UTC, same rater and exposure.
- Reference drift: `main` moved from the Step 1 baseline `05486f22` to `f9d61535` while Step 3 waited for approval. The new commits add structured `Progress: plan <done>/<total>` lines (`6a7f65e3`) and do not open the signature cache during import, so the approved plan still applies.
- Enhancement request (planning progress): superseded. `main` shipped its own planning progress in `6a7f65e3`, so local commit `51732a57` was dropped and not carried onto this branch.
- Environment: the cloud sandbox can no longer download from PHP after the pause; `curl` works through a proxy PHP does not use. Live measurements use a fresh `curl` copy of the zenmemes.com archive served on `127.0.0.1:8765`. Without `images.zip`, every run reports images as partial (exit 2), which is unrelated to this cycle.

## Stage 1 - Import planning opens the signature check cache

**Original estimate: 0.15**

- Changes:
  - `ContentImportRunner::run()` calls `SignatureCheckCache::useBeside()` with the destination database path, inside the import locks, before preview or planning.
  - New test `SignatureCheckCacheTest::testImportPlanningReusesChecksBesideTheDestinationDatabase`: a preview with an explicit database path writes the cache beside that file only, and a second preview with an empty memory cache starts no GPG process.
  - `docs/reference/v3_cli.md`: `--dry-run` now says successful signature checks are saved beside the database.
- Verification:
  - Passed: the new test failed before the change (GPG ran 4 times, no cache file) and passes after.
  - Passed: `./v3 test --smoke SignatureCheckCacheTest ContentImportPlannerTest ContentImportRecoveryTest ImportInstanceCommandTest ImportAdmissionTest ImportDeadlineRecoveryTest LegacyTimestampImportTest ImportedContentPublicationTest SignatureBudgetTest`: 532 run, 532 passed.
  - Passed: live archive, repeat import into the existing local repository, cache deleted first. Planning took 26.35 s cold, then 1.43 s and 1.41 s warm. Total run 30.95 s, then 6.81 s and 6.67 s. The cache held 292 checks after the first run.
  - Not run: full suite (maintainer runs it).
- Notes:
  - The Stage 1 dry-run question was settled by documenting it: the cache is derived state, like the lock files a preview already creates.
  - The cold run is 26 s against 17 s last week because the archive grew; Stage 2 targets that cost.
- Estimate details: original scope as Step 3 Stage 1; uncertainty was the GPG-count harness, which existed already in `SignatureCheckCacheTest`. No revisions.

**Retrospective stage-work assessment: 0.10** — less than estimated because the test harness and cache already existed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
