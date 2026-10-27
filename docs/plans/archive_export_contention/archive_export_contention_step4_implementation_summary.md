> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention)
Extended estimates: enabled
Status: Six stages locally complete; deployment and production/maintainer acceptance pending
**Cycle estimate: 0.90**

## Entry checkpoint

Planning commit: 3256d774; branch fix/archive-export-contention.
Scope: v2 / Option B; original baseline 28530e1b59b6b5d98d314bc080dda94588cc4217; R1/P2; 0.90 reaffirmed before implementation on 2026-10-26 UTC. Codex single exposed rater, prior source/reference/reproduction exposure retained. No measured labor; no acceptance claim.
Stage originals: S1/v1 0.20 freshness; S2/v1 0.25 reusable delivery; S3/v1 0.20 contention; S4/v1 0.15 retry; S5/v1 0.25 lifecycle; S6/v1 0.20 acceptance. Scope/drivers/uncertainty inherited from Step 3, not independent ratings. Shared acceptance belongs to S6.
Dependencies: Zero additions or updates authorized/proposed; use existing PHP/system tools. No schema or canonical-format change.

*— Codex · 2026-10-26 UTC*

## Stage 1 - Freshness contract

**Original estimate: 0.20**
**Retrospective stage work: 0.20**

- Changes: Content-byte/per-mode identity includes image inventory, revision and generator/guide hashes; revalidation after compression rejects source changes. Existing dirty-record and image-integrity refusals retained. ImageRestore and ImageStore explicitly require/use instance writer coordination.
- Verification: `php tests/run.php ArchiveExportTest RepositoryContentExportTest ImageContentTest`: 10/10 passed; same-size/same-mtime edits, untracked additions/removal, permissions and image identity covered. `git diff --check` passed.
- Notes: S1/v1 unchanged; warm-path representative workload timing and coordinated mutation races will be measured with cache acceptance in S6. Identity scans bytes rather than trusting timestamps. Out-of-band writers must respect the existing writer lock; no claim of atomicity against arbitrary external filesystem mutation. No schema/dependency change.

*— Codex · 2026-10-26 UTC*

## Stage 2 - Reusable downloads

**Original estimate: 0.25**
**Retrospective stage work: 0.25**

- Changes: Private identity/format-keyed artifacts with atomic directory publication and checksum validation; archive streams hold shared reader locks. HTTP delivery streams the admitted handle and closes it without deleting shared output. Existing path-based download contract unchanged.
- Verification: Focused export/image suite 11/11 passed; cold/warm reuse for all three kinds, corrupted metadata rebuild with existing reader intact, same-head source change and actual download/import compatibility. `git diff --check` passed.
- Notes: S2/v1 unchanged. Stage 1 commit 7c4a5e91. Direct export callers own/close the additional stream; PHP also closes it on request exit. Cleanup and storage bounds follow in S5; no canonical/dependency/schema changes.

*— Codex · 2026-10-26 UTC*

## Stage 3 - Coordinated contention

**Original estimate: 0.20**
**Retrospective stage work: 0.20**

- Changes: One five-second lock deadline across export coordination/writer acquisition, cache-hit fast path before global build lock and recheck afterward; writer lock never held while waiting for build lock. Other ExecutionLock callers unchanged.
- Verification: Focused 13-case run had 12 passes and one test-harness failure (parent lock descriptor inherited by child processes); explicit parent unlock corrected the fixture. ArchiveExportTest then passed 4/4, including concurrent two-tar/one-ZIP requests, exactly one compatible build, warm hit while build lock held, shared deadline and recovery after release.
- Notes: S3/v1 unchanged; Stage 2 commit 84f14eee. Deadline bounds lock waiting, not compression runtime; timed-out callers receive typed busy errors. No new config/dependencies.

*— Codex · 2026-10-26 UTC*

## Stage 4 - Failure and retry flow

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Temporary contention returns 503 with Retry-After: 5; revision conflicts retain 409 with refresh guidance; source/storage failures return 500 with operator recovery and private log details instead of paths in public bodies. Existing importer reports bounded source text and safe retry without modification.
- Verification: Focused 18-case run: 17 passed, one legacy-import assertion still expected the former 409/path leak. Updated that assertion to 500 plus private-log detail/public redaction, then the complete legacy image import/retry case passed. Actual busy wait/release, conflict, dirty-source failure and successful retry tested through Application routes; normal import and export tests passed.
- Notes: S4/v1 unchanged; Stage 3 commit cf7cb4e1. HTTP/browser Retry-After and visible phone/desktop recovery checks remain S6 acceptance. No dependency/config change.

*— Codex · 2026-10-26 UTC*

## Stage 5 - Bounded lifecycle and recovery

**Original estimate: 0.25**
**Retrospective stage work: 0.25**

- Changes: Six-entry/2-GiB private cache budget; reserve twice source bytes plus 64 MiB before a build, check filesystem space, evict unprotected stale/current artifacts as needed and refuse capacity when readers prevent reclamation. Workspaces now live on cache filesystem and abandoned staging is reclaimed under global build ownership. Cleanup takes nonblocking exclusive archive locks and preserves readers; symlink cache roots/entries refused.
- Verification: Existing export/image suite 14/14 passed, plus two new lifecycle tests passed: held-reader eviction refusal/release/retry, abandoned workspace recovery, capacity refusal, source deletion/commit during paused compression and killed-builder recovery. Recovered archive excludes deleted post, no stale metadata or staging remains. `git diff --check` passed.
- Notes: S5/v1 unchanged; Stage 4 commit 58c57cd3. Actual filesystem-full condition and browser acceptance remain S6; injected cache-capacity test is not a physical-disk-full claim. Obsolete archives with active readers remain accounted until a later miss triggers cleanup. No new settings or dependencies.

*— Codex · 2026-10-26 UTC*

## Stage 6 - Acceptance and operator handoff

**Original estimate: 0.20**
**Retrospective stage work: 0.25**

- Changes: Added actual HTTP/import retry and browser acceptance, private-storage path/relative-path correction, public-directory refusal before creation, filesystem-reservation rejection/retry, corrupt-byte recovery and safe concurrent-directory initialization. Added [operator runbook](../../runbooks/archive_export_recovery.md) and copy-audit evidence. Stage 5 commit f9fd15b8.
- Verification: Final targeted PHP command ran ArchiveExportTest, RepositoryContentExportTest, ImageContentTest, ImportInstanceCommandTest, RepositoryArchiveValidationTest, BackupPageTest and the two download smoke methods: 34 run, 33 passed, one reproduced baseline failure. The failing `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` calls the already-absent `repositoryArchiveDownloadFilename`; identical failure reproduced in an isolated archive of starting revision 28530e1b. No new failure remains. Final strengthened corruption/concurrency/storage-path checks passed 3/3 after the last small corrections.
- Browser: `node tests/browser/archive_export_browser.mjs` passed with real local HTTP, Backup links in four themes at 390/1280 widths, cold/warm tar identity, 503/Retry-After/no-store, 409 refresh guidance, successful ZIP retry/extraction, 500 repair/retry, SQLite download and Codebase link. Screenshots in `/tmp/archive-export-browser-iXh31f`; earlier phone/desktop Backup and busy screenshots in `/tmp/archive-export-browser-eymPrT` were visually inspected. No horizontal overflow in checked states. Physical devices and assistive technology not tested.
- Performance: Synthetic 19,000-post corpus (~20 MiB uncompressed record text), tar cold 9.299 seconds, warm 0.416 seconds; same 563,072-byte artifact, 16 MiB peak PHP memory. Not a qdb.us benchmark or wall-time effort measure. Warm requests still fingerprint/verify bytes.
- Recovery: Process tests cover source deletion during compression, killed-builder recovery, active-reader preservation and bounded-capacity rejection; actual filesystem free-space branch tested by requesting a reservation larger than available space without filling shared disk. Real physical ENOSPC during compression was not induced; existing failed-command/exception cleanup and interrupted-work tests cover recovery behavior, with host storage acceptance documented.
- Notes: S6/v1 scope unchanged, retrospective 0.25 reflects acceptance corrections and expanded real-source retry coverage. No new dependencies, configuration keys, schema or canonical-format changes. Full suite, deployment, merging and live qdb.us lock-holder/recovery checks were not performed. Operator guidance is the handoff for unavailable host access; no claim of production resolution.

## Study closeout and release limits

Checkpoint: 2026-10-26T02:53:15Z; v3/archive_export_contention, v2/Option B, original baseline 28530e1b59b6b5d98d314bc080dda94588cc4217, R1/P2; all frozen hashes reverified. Codex single exposed rater; original v1 0.45 and v2/entry 0.90 projections retained.
Projected cycle total: 0.90 preserved.
Retrospective cycle scope: 0.95; separate judgment including lifecycle integration and acceptance corrections, not measured labor or a stage sum.
Stages: Six planned/six locally complete, original stage-work values retained; S1–S5 retrospectives unchanged and S6 0.25. Planning 3256d774, S1 7c4a5e91, S2 84f14eee, S3 cf7cb4e1, S4 58c57cd3, S5 f9fd15b8; S6 and final handoff commits recorded in the debug closeout after creation.
Acceptance: Maintainer comfortable-fit judgment and production recovery pending; no accepted-outcome increment or independent child observation. No measured labor recorded. Zero dependency delta verified against changed paths; no manifest/lock/vendor additions, no todo.txt edits. Canonical files and export formats remain unchanged.

*— Codex · 2026-10-26 UTC*

## Merge integration verification — 2026-10-25

Integrated reusable archive exports with the image ZIP branch. Default image ZIP
and legacy image TAR use distinct cache entries; both retain protected stream
transfer, validated reuse, and the shared busy/conflict/operator-error responses.
Preserved both branches' guidance, plan indexes, study checkpoints and test entries.
Updated the image corruption HTTP assertion to the operator-error contract (500).
Added a regression covering separate ZIP/TAR artifacts and warm reuse.

Verification: 56/56 focused PHP tests passed across archive exports, image ZIP
backup/restore, image content, Backup, repository content exports, registration,
accepted retractions/receipts/transactions/maintenance, read-model retractions and
content import planning. `node tests/browser/archive_export_browser.mjs` passed
four themes at phone/desktop widths, cold/warm reuse, busy/conflict/retry, extraction
and failure recovery. Conflict-resolution diff checks passed; the full staged check
reported four existing Markdown hard-break lines in incoming retraction plans.
No dependency changes. Full suite,
physical devices and deployment were not verified during merge integration.
