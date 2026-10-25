> **Feature plan:** [Step 1](./viewer_tag_read_model_and_memoization_step1_solution_assessment.md) · [Step 2](./viewer_tag_read_model_and_memoization_step2_feature_description.md) · [Step 3](./viewer_tag_read_model_and_memoization_step3_development_plan.md) · [Step 4](./viewer_tag_read_model_and_memoization_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_read_model_and_memoization`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-24. Extended estimates: **enabled**. Stage-work values use the R1 yardstick; they are not summed into the cycle total, not hours, and not percentage-complete. Shared schema-change/verification overhead is owned where noted, not charged to every stage.

**Cycle estimate: 1.15**

## Completion Contract

- Normal entry: a logged-in viewer opens the classic board, a Forte board, a qdb board, or a thread/post permalink that shows their reaction/label state; an operator deploys the code (or runs `./v3 rebuild`).
- End-to-end outcome: viewer like/flag/vote/label state renders byte-identical to the current file-scan output, answered by indexed per-identity read-model queries (no `records/*` globbing) and served from at most one query per (record family, identity) per request (no per-tag re-query).
- Required recovery: (1) a stale or schema-mismatched read model rebuilds automatically on normal startup via the existing `schema_identity_mismatch` path in `Application::ensureReadModel()`; (2) a viewer with no reactions, an anonymous viewer, and an empty/missing records directory behave exactly as today (no tags, no errors); individual parse errors are skipped during projection as they are now.
- Deployment/external verification: `./v3 rebuild` succeeds against a current repository/database; `EXPLAIN QUERY PLAN` confirms the viewer-tag queries use the new per-identity index; a per-request trace shows zero viewer-tag globs and ≤1 query per (family, identity).
- Release condition: all stages verified, the `CONTRIBUTING.md` read-model schema-change checklist complete (version bump, spec update, prior-version rebuild regression test through normal startup), and affected focused tests green.

## Key Risks

- **High risk:** Results drift / identity leak — projection, query, or memo must never change output or cross identities. Early validation: before/after content comparison across classic board, Forte, qdb, and single-thread for a viewer with known reactions, plus no-reaction and anonymous cases (Stage 4/6). Mitigation: project at the per-(identity, target, tag) grain; query with explicit `author_identity_id` + tag predicates; key the memo by repository root + family + identity.
- **High risk:** Rebuild not triggered on deploy — new queries against an old-shaped model return empty/missing tags. Early validation: Stage 5 prior-version rebuild regression test through `Application::handle()` normal startup. Mitigation: bump `SCHEMA_VERSION` (changing `statements()` also changes the fingerprint).
- Exact-set schema test breaks — `ReadModelSchemaTest` asserts the full table/index name list. Early validation: run it in the stage that adds the table/indexes. Mitigation: update the expected list in the same stage.
- Query N+1 reintroduced on qdb — a per-call query per tag would undo the win. Early validation: per-request query-count trace on a qdb render (Stage 4/6). Mitigation: batch target ids in one `IN (…)` query per (family, identity); memo absorbs repeats (Stage 3).
- `ViewerTagLookup` signature change ripples — it is `static` and PDO-free today; switching to SQL requires a PDO handle at five call sites. Early validation: compile/tests after Stage 3. Mitigation: thread the existing read-model PDO through each caller; keep the method contract (returns `array<string,true>`) identical.

## Stage 1 — Schema: viewer-tag projection table + indexes, exact-set test in sync

**Estimate: 0.08**

- Goal: Add the read-model table(s) and per-identity indexes that hold viewer reaction/label state; keep the exact-set schema test current.
- Dependencies: none.
- Expected changes: append to `ReadModelSchema::statements()` one table `viewer_tags (record_family TEXT, target_id TEXT, author_identity_id TEXT, tag TEXT, PRIMARY KEY(record_family, target_id, author_identity_id, tag))` plus index `viewer_tags_identity_idx ON viewer_tags (author_identity_id, record_family, tag, target_id)` (the lookup predicate order). Update the expected name list in `tests/ReadModelSchemaTest.php`.
- Verification: `ReadModelSchemaTest` passes with the new objects and the fingerprint changes.
- Estimate details — driver: one table + one composite index (column order must match the query predicate) plus a known assertion-list edit; uncertainty: low–medium — only grain/column order must be right.
- Canonical components touched: `ReadModel/ReadModelSchema.php`, `tests/ReadModelSchemaTest.php`.

## Stage 2 — Builder projection + schema version/spec

**Estimate: 0.30**

- Goal: Project reaction/label records into `viewer_tags` during rebuild, and bump the schema identity + spec so the gate and docs reflect it.
- Dependencies: Stage 1.
- Expected changes: in `ReadModelBuilder::indexPostReactions()` and `indexThreadLabels()`, during the existing record iterations, write one `viewer_tags` row per (record, tag) with `record_family` `'post-reactions'`/`'thread-labels'`, `target_id` = `postId`/`threadId`, `author_identity_id`, `tag`. Skip null identities and records already excluded by the existing filters, mirroring current lookup logic (`in_array($tag, $record->tags/labels)`). Bump `ReadModelMetadata::SCHEMA_VERSION` ('26' → '27'); add a `### Viewer-tag projection (schema version 27)` entry plus table/index notes to `docs/specs/read_model_schema_v1.md`.
- Verification: a focused builder test asserts projected rows match a known fixture's reaction/label set at the per-(identity, target, tag) grain; `ReadModelMetadataTest` reports version 27 + new fingerprint; `ReadModelPostReactionsTest`/`ReadModelThreadLabelsTest` still pass (existing derived columns unchanged).
- Estimate details — driver: the core projection logic in two builder passes, matching existing filter/exclusion semantics exactly, plus version/spec; uncertainty: medium — the correctness bar is "same records the file scan would have matched."
- Canonical components touched: `ReadModel/ReadModelBuilder.php`, `ReadModel/ReadModelMetadata.php`, `docs/specs/read_model_schema_v1.md`, a new/extended builder test under `tests/`.

## Stage 3 — Rewire ViewerTagLookup to indexed SQL + request-scoped memo

**Estimate: 0.35**

- Goal: Answer `postTags`/`threadTags` from `viewer_tags` with one batched indexed query per (family, identity), memoized per request; fold in the `viewerHasThreadTag()` bypass.
- Dependencies: Stage 2.
- Expected changes: change `ViewerTagLookup` to take the read-model PDO and query `WHERE author_identity_id = ? AND record_family = ? AND tag = ? AND target_id IN (…)`; return the same `array<string,true>` shape. Add a request-scoped in-memory memo keyed by repository-root/PDO + family + identity holding the identity's full tag set, so repeat calls (other tags, other call sites, qdb per-tag loop) filter in memory. Thread the PDO through the five call sites (`BoardPageController`, `ForteBoardController`, `QdbBoardPolicy`, `ThreadAndPostPageController` ×2) and redirect `ThreadAndPostPageController::viewerHasThreadTag()` (L280) through the lookup, deleting its direct `thread-labels` glob.
- Verification: a `ViewerTagLookup` unit test asserts results equal the pre-change file-scan output for a fixture viewer (with/without reactions, anonymous) and that a query-count probe shows ≤1 query per (family, identity) across repeated calls; project compiles and existing board/thread/qdb tests pass.
- Estimate details — driver: SQL rewrite + memo correctness across five call sites + signature threading + bypass removal — the largest correctness surface in the cycle; uncertainty: medium–high (per-identity keying must match current filter logic exactly; memo must be request-scoped).
- Canonical components touched: `ReadModel/ViewerTagLookup.php`, `Http/BoardPageController.php`, `Http/ForteBoardController.php`, `Qdb/QdbBoardPolicy.php`, `Http/ThreadAndPostPageController.php`.

## Stage 4 — Parity + query/scan trace across call sites

**Estimate: 0.15**

- Goal: Prove byte-identical viewer-tag state and the scan/query reductions on all four surfaces.
- Dependencies: Stage 3.
- Expected changes: none to application source beyond test scaffolding; a parity test (or extended existing board/thread tests) comparing rendered viewer-tag state against a captured pre-change baseline for classic board, Forte, qdb, and single-thread, each for a viewer with known reactions and for the no-reaction/anonymous cases; a trace assertion of zero `records/*` globs for viewer-tag state and ≤1 query per (family, identity).
- Verification: parity equal across all surfaces/cases; trace assertions hold.
- Estimate details — driver: capturing clean before/after baselines across four routes + the trace probe; uncertainty: medium — baseline capture across surfaces is the variable part.
- Canonical components touched: board/Forte/qdb/thread controllers (observed), test files under `tests/`.

## Stage 5 — Prior-version rebuild regression through normal startup

**Estimate: 0.15**

- Goal: Prove a pre-27 model rebuilds on normal startup before the new viewer-tag query runs (the `CONTRIBUTING.md` requirement).
- Dependencies: Stages 1–3.
- Expected changes: add/extend a regression test (mirroring the corrected `ReadModelHotIndexRebuildTest`) that seeds a read model stamped with a prior schema identity and lacking `viewer_tags`, drives `Application::handle('GET', <a logged-in viewer thread/board route>)`, and asserts `ensureReadModel()` detected `schema_identity_mismatch`, rebuilt, created `viewer_tags` + index, and rendered correct viewer-tag state. Planned helper (no implementation here): `seedReadModelWithStaleSchemaIdentity(string $databasePath): void`.
- Verification: the test fails before Stages 1–2 (no table / old identity never rebuilds) and passes after; asserts rebuild-then-query ordering, table/index presence (`PRAGMA index_list`/`sqlite_master`), and `rebuild_reason = schema_identity_mismatch`.
- Estimate details — driver: startup-driven stale-identity harness (reuses the hot-index pattern and `RebuildHistoryFixture`); uncertainty: medium — exercising startup with a logged-in viewer identity in a test.
- Canonical components touched: `Application::ensureReadModel()` (behavior exercised, not changed), `ReadModel/ReadModelMetadata.php` (identity read), a test file under `tests/`.

## Stage 6 — End-to-end rebuild + query-plan verification

**Estimate: 0.10**

- Goal: Confirm `./v3 rebuild` succeeds and the viewer-tag queries use the per-identity index end to end.
- Dependencies: Stages 1–5.
- Expected changes: none to application source; verification only. Record evidence in the Step 4 summary.
- Verification: `./v3 rebuild` succeeds (candidate validate → promote → offline snapshot); `EXPLAIN QUERY PLAN` on the `ViewerTagLookup` queries shows `SEARCH viewer_tags USING INDEX viewer_tags_identity_idx`; the affected focused read-model/board/thread/qdb tests pass. Owns the shared end-to-end/deployment verification for the cycle.
- Estimate details — driver: operator rebuild + query-plan inspection + focused-suite run; uncertainty: low–medium (sandbox PHP linkage note from `read_model_hot_indexes` applies).
- Canonical components touched: `ReadModel/ViewerTagLookup.php` queries (observed), `./v3 rebuild` (operator path).

## Effort snapshot and approval

**Cycle estimate: 1.15** — reaffirmed at Step 3.

- Step 3 handoff projected total (combined scope): **1.15 reaffirmed** (unchanged from Step 2), independently judged and **not** the sum of the stage-work values above. Above the 1.0 split-discussion threshold by deliberate user choice; the six stages fit a coherent single branch (schema → projection → rewire+memo → parity → rebuild-regression → e2e), so no split is raised at this step. If implementation reveals the rewire/parity surface (Stages 3–4) is larger than projected, raise a split back into the two original cycles (schema+projection as B; rewire+memo as A) before continuing.
- Stage-work values are 2026-10-24 extended-mode annotations (S1 0.08, S2 0.30, S3 0.35, S4 0.15, S5 0.15, S6 0.10); each covers only that stage's changes, verification, and ordinary correction. Whole-cycle planning overhead is not re-charged per stage. Single exposed, non-blind rater; not measured effort, hours, an approval, or a completed outcome.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping; predecessor artifacts retained unchanged. Planning writes remain uncommitted.
- Await `Approved Step 3` before creating the feature branch, organizing plans, making the planning commit, or implementing.

### Presentation revision — 2026-10-24

User requested a preview with clearly separated estimate values. Added standalone estimate lines and separated supporting detail; numbers, scope, uncertainty and approval state are unchanged. This is a formatting revision, not a new rating or approval. Railspike instruction changes are deferred until the user reviews this preview.
