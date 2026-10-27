# Import without static rendering — one-shot outcome

User opted out of staged Railspike after the [initial assessment](./import_without_static_rendering_step1_solution_assessment.md). Implemented Option A directly; no Step 2/3 gate or stage commits apply. Starting revision `c86b89314602a17cf435448bb66053f4d750150f`; the user subsequently explicitly requested committing the accumulated changes, including the preserved reference-recovery records. No merge, push or deployment.

## Behavior

- `import-instance`, duplicate imports and `--resume` retire stale forum HTML before data refresh; they never invoke the static page builder or fingerprint/copy presentation assets. PHP serves imported content. Explicit `./v3 build-static` remains available and unchanged.
- Read-model candidate validation/promotion and public offline snapshot/update publication remain; private destinations publish no public offline data. Failures preserve the pending journal for same-destination remote-free resume with no duplicate commit, including journals created under the prior publisher.
- Reuse existing strict invalidation, with an optional documentation exclusion for imports. Both valid and incomplete legacy docs remain untouched; no recursive docs traversal is introduced. Other callers retain existing invalidation defaults. Independent docs publication/serving remains unchanged.
- Updated CLI help/progress and import runbook/reference. No schema, navigation, reader UI, third-party dependency or todo changes.

## Verification

`php tests/run.php ImportedContentPublicationTest ImportInstanceCommandTest ContentImportRecoveryTest DocsPublicationFlowTest LegacyDocsMigrationTest RetractionSweepTest` — **Summary: 41 run, 41 passed, 0 failed** on final production source, local WSL. Full/shared explicit-build tests remain in that run.

Covers actual HTTP archive imports/duplicates/private mode/legacy images, served thread/tag/API/offline data, closed output pipes, five publication-failure checkpoints, write-boundary recovery, one commit/cleared journal, remote-free CLI resume with missing/damaged/legacy docs, concurrent docs success/failure, strict stale pointer/direct HTML retirement, preserved incomplete docs, current offline data and cleanup safety. Cold/new/duplicate CLI tests trap the static builder to throw and break the tag/docs templates; imports still succeed with no rendering/fingerprinting progress. Complete CLI and served HTTP checks provide normal-path evidence; no visual UI changed.

Earlier test iterations exposed obsolete static-output/message assertions and a fixture count including bootstrap posts; corrected them. One intermediate run loaded an older invalidator definition before its optional argument was added; the complete final run was restarted on stable source and passed. Syntax checks for changed PHP, CLI help and `git diff --check` passed. Full repository suite, production-host acceptance and an already-running user's import were not exercised. No new dependency or installed tool.

## Study closeout

UTC 2026-10-23T18:06:11.235757+00:00; stable `v3/import_without_static_rendering`, scope v1 / R1 / P2, predecessor `v3/docs_independent_publication`. Intake/Step 1 projection **0.55** preserved; **retrospective scope assessment 0.55**, Codex single exposed rater, with existing rendering/invalidation/promotion/offline machinery reused. No subsequent prospective checkpoint fabricated after one-shot opt-out; actual active work unknown. Exact host-local R1 hashes verified at intake, unchanged. Local implementation complete without planned stages; maintainer comfortable-fit/acceptance pending, accepted count unchanged. One observation across worktrees; no upload.
