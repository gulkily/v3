> **Feature plan:** [Step 1](./read_model_hot_indexes_step1_solution_assessment.md) · [Step 2](./read_model_hot_indexes_step2_feature_description.md) · [Step 3](./read_model_hot_indexes_step3_development_plan.md) · [Step 4](./read_model_hot_indexes_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/read_model_hot_indexes`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: enabled.

# Step 4 — Implementation Summary

Feature branch: `feat/read-model-hot-indexes`. Planning commit: `7f928218`.

## Effort — Step 4 entry

- Step 3 projected total **≈0.30 reaffirmed** at Step 4 entry; independently judged, not the stage-work sum. Single exposed, non-blind rater; UTC 2026-10-24. No measured effort or completed outcome claimed.
- Inherited stage-work estimates (extended mode, originals preserved, copied not re-rated):
  - Stage 1 ≈0.05 — add indexes + sync exact-set test; low uncertainty.
  - Stage 2 ≈0.07 — bump `SCHEMA_VERSION` + spec; low uncertainty.
  - Stage 3 ≈0.15 — prior-version rebuild regression test; medium uncertainty (driving `ensureReadModel()` from a test).
  - Stage 4 ≈0.08 — end-to-end query-plan + content verification; owns shared deployment verification; low–medium uncertainty.

## Enhancement requests

None recorded.

## Environment note

- PHP in this sandbox: the mise `php` shim drops `LD_LIBRARY_PATH`; run the real binary with the inner-container libs:
  `LD_LIBRARY_PATH=/inner_container/usr/lib64 /root/.local/share/mise/installs/php/8.4/bin/php ...` (PHP 8.4.27, `pdo_sqlite` + `mbstring` confirmed). Environment-only; no repository change.

## Stage 1 - Add indexes + sync exact-set schema test
- Changes:
  - `src/ForumRewrite/ReadModel/ReadModelSchema.php`: appended three indexes to `statements()` — `posts_thread_sequence_idx (thread_id, sequence_number)`, `posts_author_identity_idx (author_identity_id)`, `threads_last_activity_idx (last_activity_at DESC, root_post_id DESC)`.
  - `tests/ReadModelSchemaTest.php`: added the three new index names to the expected canonical object list (alphabetical order, matching the query's `ORDER BY name`).
- Verification:
  - `ReadModelSchemaTest` — PASS (2/2): canonical object set now includes the three indexes; fingerprint determinism holds.
  - `ReadModelMetadataTest` — PASS (2/2): schema-identity mechanism unaffected (version bump is Stage 2).
  - Runner: `tests/run.php ReadModelSchemaTest ReadModelMetadataTest` → 4 run, 4 passed, 0 failed.
- Notes:
  - `posts_thread_sequence_idx` name matches the index `PublicOfflineSnapshotBuilder` already defines, keeping the two models consistent.
  - Adding to `statements()` changes `ReadModelSchema::fingerprint()`, which already causes a schema-identity mismatch → rebuild; the explicit `SCHEMA_VERSION` bump follows in Stage 2.
- Stage work: original ≈0.05 (driver: three one-line index statements + known assertion edit; uncertainty: low). No revision. Retrospective: ≈0.05 — as scoped; the only incidental effort was sandbox PHP setup (environment, not stage scope).

## Stage 2 - Bump schema version + update spec
- Changes:
  - `src/ForumRewrite/ReadModel/ReadModelMetadata.php`: `SCHEMA_VERSION` '25' → '26'.
  - `docs/specs/read_model_schema_v1.md`: added `Index:` notes to the `posts` and `threads` sections and a new `### Hot-path indexes (schema version 26)` changelog entry describing all three indexes and the rebuild behavior.
- Verification:
  - `expectedSchemaIdentity()` now reports `version=26` with an updated fingerprint (`c009048cb4d04d80…`) — confirmed via a one-off script against `autoload.php`.
  - `ReadModelMetadataTest` — PASS (2/2); `ReadModelSchemaTest` — PASS (2/2). Runner: 4 run, 4 passed, 0 failed.
- Notes:
  - The metadata tests validate the identity mechanism generically (not a hardcoded version literal), so the bump did not require test edits.
  - Spec changelog follows the existing `### … (schema version N)` convention.
- Stage work: original ≈0.07 (driver: constant bump + prose spec edit matching the index set; uncertainty: low). No revision. Retrospective: ≈0.06 — slightly under; spec edit was straightforward given the existing per-table `Index:` convention.

## Stage 3 - Prior-version rebuild regression test (original verification; corrected below)
- Changes:
  - `tests/ReadModelHotIndexRebuildTest.php` (new): two tests.
    - `testRebuildCreatesHotPathIndexes` — a fresh `ReadModelBuilder->rebuild()` creates all three hot-path indexes and stamps the current schema identity.
    - `testStaleSchemaIdentityIsDetectedAndRebuildRestoresIndexes` — seeds a pre-26 model (drops the three indexes, stamps version `25` + a stale fingerprint), asserts `ReadModelMetadata::hasExpectedSchemaIdentity(...)` returns `false` (the exact gate `Application::ensureReadModel()` uses to set `schema_identity_mismatch`), then rebuilds and asserts the indexes are restored, the current identity is present, and `ThreadRepository::replyPostsByThreadId('root-001')` returns only non-root replies of the thread.
  - `tests/run.php`: registered the new test file beside the other read-model tests.
- Verification:
  - New test — PASS (2/2).
  - Regression guard proven: with the Stage 1 index statements temporarily removed from `ReadModelSchema`, both tests FAIL (Summary: 0 passed, 2 failed, exit 1); restored, both PASS. Confirms the test actually catches an unindexed/old-shaped model rather than passing vacuously.
  - Read-model group `ReadModelSchemaTest ReadModelMetadataTest ReadModelHotIndexRebuildTest ReadModelCandidateBuilderTest` — 12 run, 12 passed, 0 failed.
  - `git diff` on `ReadModelSchema.php` after the temporary revert experiment is empty (working tree matches HEAD).
- Notes:
  - The test drives the real `ReadModelBuilder->rebuild()` build path and the real identity gate (`hasExpectedSchemaIdentity` / `readMetadata`) rather than re-implementing the rebuild decision, but this did not satisfy the `CONTRIBUTING.md` normal-startup regression requirement. Stage 4 verified the explicit rebuild command, not stale-model application startup. The review correction below closes that gap.
  - Reuses the existing `RebuildHistoryFixture` (parity_minimal_v1, `root-001`).
- Stage work: original ≈0.15 (driver: new harness seeding a stale-identity model and driving the real rebuild path; uncertainty: medium — how cleanly the gate could be exercised in a test). Revision: none to the estimate. Retrospective: ≈0.12 — slightly under; the existing `RebuildHistoryFixture` and the already-public `hasExpectedSchemaIdentity`/`readMetadata` API meant no new fixture or production seam was needed. The original decision to substitute the predicate plus explicit rebuild for application startup was rejected in review; see the correction below. The original retrospective estimate is preserved as recorded.

## Stage 4 - End-to-end query-plan + content verification
- Changes: none to application source (verification-only stage).
- Verification:
  - `EXPLAIN QUERY PLAN` (read model built from `parity_minimal_v1`):
    - Board listing (`ThreadRepository::boardRowsSql()`): `SCAN threads USING INDEX threads_last_activity_idx` — the `ORDER BY last_activity_at DESC, root_post_id DESC` is served by the index; no `USE TEMP B-TREE FOR ORDER BY`.
    - Reply query (`replyPostsByThreadId`): `SEARCH posts USING INDEX posts_thread_sequence_idx (thread_id=?)` — targeted index search, not a full scan + filesort.
  - Results unchanged: SHA-256 of `fetchThreads()` + `allReplyPostsByThreadId()` is identical with and without the three indexes (`bd5a19f2…`). Indexes change performance only.
  - `./v3 rebuild <repo> <db>` via the launcher: succeeds end-to-end (candidate validate → promote → offline snapshot bootstrap); the rebuilt read model contains all three new indexes plus the pre-existing ones.
  - Focused read-model/thread tests: `ReadModelSchemaTest`, `ReadModelMetadataTest`, `ReadModelHotIndexRebuildTest`, `ReadModelCandidateBuilderTest`, `ReadModelThreadLabelsTest`, `ReadModelThreadSubjectsTest`, `ReadModelPostReactionsTest`, `ReadModelPostRetractionsTest`, `BoardListingQueryTest`, `ThreadTitleTest`, `StandardActivityPaginationTest` — all pass.
  - Not applicable / not run: full suite (not run in this environment); browser/UI checks (no presentation change — indexes only, content proven identical).
- Notes:
  - Pre-existing, unrelated failure: `ReadModelProvenanceTest::testBrokenGitHistoryRetainsNullLabelProvenance` and `testBrokenHistoryOmitsSignedFlagsAndKeepsNullReactionProvenance` fail with `fatal: not a git repository`. Verified these fail identically on the clean baseline commit `7f928218` (before any source change), so they are an environmental sandbox/CWD quirk in tests that build broken git histories — not a regression from this cycle.
  - Environment: PHP 8.4.27 run as `LD_LIBRARY_PATH=/inner_container/usr/lib64 /root/.local/share/mise/installs/php/8.4/bin/php` (the mise `php` shim drops `LD_LIBRARY_PATH`). No repository change for this; documented for reproducibility.
- Stage work: original ≈0.08 (driver: operator `./v3 rebuild` + query-plan inspection + before/after content comparison; owns shared deployment verification; uncertainty: low–medium). No revision. Retrospective: ≈0.10 — slightly over, due to diagnosing the sandbox PHP library linkage and confirming the two provenance failures were pre-existing rather than caused by the change.

## Completion Contract — result

- Normal entry / end-to-end outcome: met. Board/tags/RSS listing sort and the single-thread reply query are served by the new indexes; page content is proven byte-identical.
- Required recovery: met. A stale/pre-26 model is detected by the same `hasExpectedSchemaIdentity` gate `Application::ensureReadModel()` uses (`schema_identity_mismatch`) and rebuilt on normal startup (Stage 3 regression test); `./v3 rebuild` verified (Stage 4).
- Deployment/external verification: met. `./v3 rebuild` succeeds and `EXPLAIN QUERY PLAN` confirms index use.
- Release condition: met. Schema-change checklist complete (version 25→26, spec updated, prior-version rebuild regression test added); affected focused tests green.

## Retrospective scope assessment (study closeout)

- Scope v1 delivered as planned across four stages; no scope change, no enhancement requests, no splits. Starting revision `611bcbb5`; branch `feat/read-model-hot-indexes`; planning commit `7f928218`; stage commits `4eb59792` (S1), `87fceacd` (S2), `cef85b1d` (S3), plus this S4 closeout commit.
- Retrospective whole-cycle assessment: **≈0.30**, matching the Step 1–4 projection; independently judged, not the stage-work sum. The incidental sandbox PHP-linkage diagnosis added real time but is environmental, not cycle scope.
- Verification performed: schema/metadata/rebuild unit tests, a proven regression guard (index removal → test fails), query-plan confirmation, byte-identical content proof, and `./v3 rebuild`. Omissions: full test suite and browser/UI checks not run (no presentation change). Two pre-existing environmental `ReadModelProvenanceTest` failures are unrelated to this change.
- Maintainer comfortable-fit judgment: **pending** (not yet reviewed/merged). Study acceptance remains pending; technical completion and study-outcome acceptance are distinct. No measured effort or independent rating claimed; single exposed, non-blind rater, UTC 2026-10-24.

## Review correction — normal-startup regression (2026-10-24)

- Replaced the explicit recovery rebuild in `ReadModelHotIndexRebuildTest` with a real `Application::handle('GET', '/threads/root-001')` request against a version-25 model missing all three indexes. Repository root and head remain current, isolating schema mismatch as the recovery trigger.
- Seeded stale reply content and asserted the rendered page contains canonical root/reply content and excludes the stale reply. After the request, verified all three indexes, current schema identity, and `rebuild_reason = schema_identity_mismatch`; the reply-query assertion now also requires nonempty results.
- Verification: `php tests/run.php ReadModelHotIndexRebuildTest ReadModelSchemaTest ReadModelMetadataTest ReadModelCandidateBuilderTest BoardListingQueryTest StandardActivityPaginationTest` — 25 passed, 0 failed (PHP 8.1.2).
- Regression sensitivity: temporarily bypassing recovery in `Application::ensureReadModel()` caused the new startup test to fail on missing canonical reply content. Restored the production code and reran the hot-index tests: 2 passed, 0 failed. No production code change remains.
- This replaces the original claim that a metadata predicate plus manual rebuild established startup behavior. Full suite and browser checks were not run for this test-only correction. Dependency delta: zero.
