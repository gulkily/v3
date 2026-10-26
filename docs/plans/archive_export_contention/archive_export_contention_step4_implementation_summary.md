> **Feature plan:** [Step 1](./archive_export_contention_step1_solution_assessment.md) · [Step 2](./archive_export_contention_step2_feature_description.md) · [Step 3](./archive_export_contention_step3_development_plan.md) · [Step 4](./archive_export_contention_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/archive_export_contention; [ledger](../effort_estimation_trial.md#cycle-v3archive_export_contention)
Extended estimates: enabled
Status: Step 4 in progress; Step 3 approved
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
