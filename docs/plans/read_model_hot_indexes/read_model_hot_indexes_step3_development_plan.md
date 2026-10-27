> **Feature plan:** [Step 1](./read_model_hot_indexes_step1_solution_assessment.md) · [Step 2](./read_model_hot_indexes_step2_feature_description.md) · [Step 3](./read_model_hot_indexes_step3_development_plan.md) · [Step 4](./read_model_hot_indexes_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/read_model_hot_indexes`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-24. Extended estimates: enabled (from Step 3). Stage-work values use the R1 yardstick; they are not summed into the cycle total, not hours, and not percentage-complete. Shared schema-change/verification overhead is owned where noted, not charged to every stage.

## Completion Contract

- Normal entry: a reader opens the board, a tag page, the RSS feed, or a thread permalink; an operator deploys the code (or runs `./v3 rebuild`).
- End-to-end outcome: the board listing sort and the single-thread reply query are served via new indexes instead of full scans + filesorts, and every affected page renders content identical to pre-change output.
- Required recovery: a stale or schema-mismatched read model rebuilds automatically on normal startup via the existing `schema_identity_mismatch` path in `Application::ensureReadModel()`; existing rebuild-failure errors (write/permissions/cache/high-traffic maintenance) remain unchanged.
- Deployment/external verification: `./v3 rebuild` succeeds against a current repository/database; `EXPLAIN QUERY PLAN` confirms index use on the target queries.
- Release condition: all stages verified, the `CONTRIBUTING.md` read-model schema-change checklist complete (version bump, spec update, prior-version rebuild regression test), and affected focused tests green.

## Key Risks

- **High risk:** Results drift — an index must never change query output. Early validation: before/after content comparison on board/tags/RSS/thread plus `EXPLAIN QUERY PLAN`. Mitigation: add indexes only; no query-shape changes in this cycle.
- Rebuild not triggered on deploy — a deployed instance could run new queries against an old-shaped model. Early validation: the Stage 3 prior-version regression test exercising `hasExpectedSchemaIdentity`. Mitigation: bump `SCHEMA_VERSION` (changing `statements()` also changes the fingerprint).
- Existing exact-set schema test breaks — `ReadModelSchemaTest` asserts the full table/index name list. Early validation: run that test. Mitigation: update its expected list in the same stage that adds the indexes.
- Rebuild cost / larger read model — Early validation: `./v3 rebuild` timing on a representative model. Mitigation: three narrow indexes only; no covering-index tuning.

## Stage 1
- Goal: Add the three indexes to the canonical schema and keep the exact-set schema test in sync.
- Dependencies: none.
- Expected changes: append three `CREATE INDEX` statements to `ReadModelSchema::statements()` — `posts(thread_id, sequence_number)`, `posts(author_identity_id)`, `threads(last_activity_at DESC, root_post_id DESC)` (names consistent with existing `*_idx` convention; reuse `posts_thread_sequence_idx` as defined in `PublicOfflineSnapshotBuilder`). Update the expected name list in `tests/ReadModelSchemaTest.php::testCanonicalStatementsCreateExpectedTablesAndIndexes`.
- Verification approach: run `ReadModelSchemaTest` (both canonical-objects and fingerprint-determinism assertions pass with the new list); confirm the fingerprint value changes versus prior.
- Stage work estimate: **≈0.05**; driver: three one-line `CREATE INDEX` statements plus a known assertion-list edit; uncertainty: low — only the index names/column order must be right.
- Risks or open questions:
  - Impact: exact-set test fails if the name list is not updated.
  - Early warning / validation: `ReadModelSchemaTest` run.
  - Mitigation: update the assertion in this same stage.
- Canonical components/API contracts touched: `ReadModel/ReadModelSchema.php` (`statements()`), `tests/ReadModelSchemaTest.php`.

## Stage 2
- Goal: Bump the schema version and update the schema spec so the identity gate and documentation reflect the change.
- Dependencies: Stage 1.
- Expected changes: increment `ReadModelMetadata::SCHEMA_VERSION` ('25' → '26'); add the three indexes to `docs/specs/read_model_schema_v1.md` (document section, no SQL dumps beyond the spec's existing style).
- Verification approach: run `ReadModelMetadataTest`; confirm `expectedSchemaIdentity()` now reports version 26 and the new fingerprint; spec review for accuracy and links.
- Stage work estimate: **≈0.07**; driver: trivial constant bump plus a prose spec edit that must match the new index set exactly; uncertainty: low — risk is only spec/version drift, caught by the test and review.
- Risks or open questions:
  - Impact: version/spec drift if either is missed.
  - Early warning / validation: `ReadModelMetadataTest`.
  - Mitigation: both edits in one stage, same commit.
- Canonical components/API contracts touched: `ReadModel/ReadModelMetadata.php` (`SCHEMA_VERSION`), `docs/specs/read_model_schema_v1.md`.

## Stage 3
- Goal: Prove that an older/mismatched read model rebuilds on normal startup before a changed query runs (the `CONTRIBUTING.md` regression-test requirement).
- Dependencies: Stages 1–2.
- Expected changes: add/extend a regression test that builds a read model stamped with a prior schema identity (old `SCHEMA_VERSION`/fingerprint in `metadata`), then invokes the normal startup path so `Application::ensureReadModel()` detects `schema_identity_mismatch` and rebuilds; after rebuild, assert the new indexes exist and the board/reply query returns correct rows. Planned helper signature (no implementation): `seedReadModelWithStaleSchemaIdentity(string $databasePath): void`.
- Verification approach: the new test fails before Stages 1–2 (old identity never rebuilds / indexes absent) and passes after; it asserts rebuild-then-query ordering and index presence.
- Stage work estimate: **≈0.15**; driver: new test harness that seeds a stale-identity read model and drives the real startup/`ensureReadModel()` rebuild path — the only stage writing non-trivial new logic; uncertainty: medium — depends on how cleanly the startup path can be invoked in a test without broad fixture setup.
- Risks or open questions:
  - Impact: without this the deploy-rebuild guarantee is unverified.
  - Early warning / validation: run the new test on the full change set.
  - Mitigation: assert on `PRAGMA index_list`/`sqlite_master` after startup, not just on query results.
- Canonical components/API contracts touched: `Application::ensureReadModel()` (behavior exercised, not changed), `ReadModel/ReadModelMetadata.php` (identity read), a test file under `tests/` (new or extended, e.g. `ReadModelSchemaRebuildTest`).

## Stage 4
- Goal: Confirm end-to-end that the hot queries use the indexes and affected pages render unchanged content.
- Dependencies: Stages 1–3.
- Expected changes: none to application source; verification only (operator/`./v3 rebuild` run plus query-plan and content checks). Record evidence in the Step 4 summary.
- Verification approach: `./v3 rebuild` succeeds; `EXPLAIN QUERY PLAN` on `ThreadRepository::boardRowsSql()` and `replyPostsByThreadId()` shows the new indexes and no `USE TEMP B-TREE FOR ORDER BY` on those paths; board, a tag page, RSS, and a thread permalink render content identical to a pre-change capture; run affected focused read-model/thread tests.
- Stage work estimate: **≈0.08**; driver: no code — operator `./v3 rebuild`, query-plan inspection, and before/after content comparison across four routes; owns the shared end-to-end/deployment verification for the cycle; uncertainty: low–medium — mostly mechanical, but capturing a clean before/after content baseline across four routes is the variable part.
- Risks or open questions:
  - Impact: a planner that ignores an index, or a content difference, would indicate a mis-defined index.
  - Early warning / validation: query-plan output and byte-level content comparison.
  - Mitigation: if an index is unused, re-check column order against the `ORDER BY`; do not add covering columns speculatively.
- Canonical components/API contracts touched: `ReadModel/ThreadRepository.php` (queries observed), `./v3 rebuild` (operator path).

## Effort snapshot and approval

- Step 3 handoff projected total: **≈0.30 reaffirmed** (unchanged from Steps 1–2), independently judged and **not** the sum of the stage-work values above. The plan accounts for the full schema-change checklist, the prior-version rebuild regression test, and query-plan/content verification; no new scope. Scope is unchanged — enabling extended estimates added per-stage annotations only, not work.
- Stage-work values are new 2026-10-24 annotations under extended mode (Stage 1 ≈0.05, Stage 2 ≈0.07, Stage 3 ≈0.15, Stage 4 ≈0.08); each covers only that stage's changes, verification and ordinary correction. Whole-cycle planning overhead is not re-charged per stage. Single exposed, non-blind rater; not measured effort, hours, an approval, or a completed outcome.
- Documentation boundary includes this cycle's plans/debug log, later index organization, and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 3` before creating the feature branch, organizing plans, making the planning commit, or implementing.
