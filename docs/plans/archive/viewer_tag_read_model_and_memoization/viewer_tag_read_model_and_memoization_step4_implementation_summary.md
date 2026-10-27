> **Feature plan:** [Step 1](./viewer_tag_read_model_and_memoization_step1_solution_assessment.md) · [Step 2](./viewer_tag_read_model_and_memoization_step2_feature_description.md) · [Step 3](./viewer_tag_read_model_and_memoization_step3_development_plan.md) · [Step 4](./viewer_tag_read_model_and_memoization_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_read_model_and_memoization`; [ledger](../../effort_estimation_trial.md), standing enrollment P2. Extended estimates: enabled.

# Step 4 — Implementation Summary

Feature branch: `feat/viewer-tag-read-model-and-memoization`. Planning commit: `7a22f490`.

## Effort — Step 4 entry

- Step 3 projected total **1.15 reaffirmed** at Step 4 entry; independently judged, not the stage-work sum. Above the 1.0 split-discussion threshold by deliberate user choice; no split was taken — the six stages landed on one coherent branch. Single exposed, non-blind rater; UTC 2026-10-24. No measured effort or completed-study outcome claimed.
- Inherited stage-work estimates (extended mode, originals preserved): S1 0.08, S2 0.30, S3 0.35, S4 0.15, S5 0.15, S6 0.10.

## Environment note

- PHP runs natively in this sandbox (no `LD_LIBRARY_PATH` shim workaround needed, unlike the `read_model_hot_indexes` cycle). `pdo_sqlite` + `mbstring` present.

## Scope decisions recorded during implementation

- **Exclusions (user directive "ignore the exclusions"):** the `viewer_tags` projection mirrors the former file scan's record set exactly — no `inactive`/`excludedPublicationPosts` exclusion — so viewer like/flag/vote/label results are byte-identical, including on retracted/removed-source threads. The broader removal of those exclusions from the label/score/vote derivation was explicitly **not** done (separate future change).
- **Identity notation (user clarification):** `openpgp-` vs `openpgp:` are intermixed and not standardized. The projection stores `author_identity_id` verbatim and lookups compare verbatim, so results match under either form or a mix. No normalization introduced.

## Stage 1 — viewer_tags table + per-identity index (`794ba967`)
- Changes: `ReadModelSchema::statements()` gains `viewer_tags (record_family, target_id, author_identity_id, tag)` with `PRIMARY KEY(record_family, target_id, author_identity_id, tag)` and `viewer_tags_identity_idx (author_identity_id, record_family, tag, target_id)`; `tests/ReadModelSchemaTest.php` expected-object list synced.
- Verification: `ReadModelSchemaTest` + `ReadModelMetadataTest` 4/4 pass; fingerprint changes.
- Stage work: original 0.08; retrospective ≈0.08 — as scoped.

## Stage 2 — builder projection + schema version/spec (`127dd2e4`)
- Changes: `ReadModelBuilder::indexPostReactions()`/`indexThreadLabels()` collect `viewer_tags` rows from the full loaded record set (ignoring exclusions, skipping null identities) and write them via a new `writeViewerTags()` (`INSERT OR IGNORE`). `SCHEMA_VERSION` 26→27; spec entry "Viewer-tag projection (schema version 27)". **Also added `viewer_tags` to `dropSchema()`** — without it, in-place rebuilds collided (`table viewer_tags already exists`); caught by the hot-index v25→current startup rebuild test.
- Verification: new `ReadModelViewerTagsProjectionTest` asserts projection == file-scan membership across all projected (family, tag) pairs + null-identity exclusion; 34 read-model/candidate/board/activity tests pass.
- Stage work: original 0.30; retrospective ≈0.32 — slightly over, due to discovering and fixing the `dropSchema` coupling.

## Stage 3 — rewire ViewerTagLookup to SQL + request-scoped memo (`179d5496`)
- Changes: `ViewerTagLookup` now takes a `PDO` and answers from `viewer_tags` with one indexed query per (family, identity), memoized for the connection's life; `postTags`/`threadTags` filter the identity's cached tag→targets map in memory (collapsing the qdb per-tag loop and repeated call sites to one query per family). Threaded the read-model PDO through all five call sites (`BoardPageController`, `ForteBoardController`, `ThreadAndPostPageController` ×qdb-votes + `viewerPostTagsForPosts`, `QdbBoardPolicy` via a constructor PDO injected at the `Application` build site). Folded `ThreadAndPostPageController::viewerHasThreadTag()` through the lookup and deleted its `thread-labels` glob; removed the now-unused `CanonicalRecordRepository` import.
- Verification: 35 board/forte/qdb/thread/projection tests pass.
- Stage work: original 0.35; retrospective ≈0.35 — as scoped (the five-site rewire did not exceed projection; no split needed).

## Stage 4 — parity + query-count proof; memo-key fix (`a6ce8277`)
- Changes: `ViewerTagLookupParityTest` reproduces the former glob+parse algorithm as an oracle and asserts the SQL lookup returns byte-identical results across post/thread families, multiple tags, a foreign identity (no leak), an unknown identity, and empty target sets; a `CountingPdo` asserts ≤1 `viewer_tags` query per (family, identity). **Fix:** the memo is keyed by a `WeakMap`-assigned connection id instead of `spl_object_id()`. `spl_object_id` can recycle an id after a PDO is freed, which could have served one identity's cached map on a different reused connection — an identity-leak/stale-result risk. Caught by the query-count assertion (expected 2, got 1) before it could ship.
- Verification: parity + projection tests pass.
- Stage work: original 0.15; retrospective ≈0.18 — slightly over, from diagnosing and fixing the object-id-reuse hazard the test exposed.

## Stage 5 — prior-version rebuild through normal startup (`6ac03208`)
- Changes: `ReadModelViewerTagsRebuildTest` seeds a pre-27 model (viewer_tags dropped, stale identity), drives `Application::handle('GET', '/threads/root-001')`, and asserts startup detected `schema_identity_mismatch`, rebuilt, restored `viewer_tags` + index, and served fresh (not stale) content. Mirrors the corrected `ReadModelHotIndexRebuildTest` startup-driven pattern.
- Verification: 3 rebuild tests pass standalone and together.
- Stage work: original 0.15; retrospective ≈0.12 — under; the hot-index pattern and `RebuildHistoryFixture` were directly reusable.

## Stage 6 — incremental write-path projection + end-to-end (`ef7c29da`)
- Changes: discovered during full-suite verification that the **incremental** updater (warm-DB write path) did not populate `viewer_tags`, so viewer state vanished after an API reaction write until the next full rebuild. Added `IncrementalReadModelUpdater::refreshViewerTagsForTarget()`, called from `applyThreadLabelWrite()`/`applyPostReactionWrite()`: delete the affected (family, target) rows and re-insert from the full unfiltered record scan, mirroring the builder projection. Updated `testForteReplyLikesRenderAndRestoreViewerState` (which wrote a reaction out-of-band and relied on the former file scan's instant filesystem visibility) to rebuild after the direct write — the read model is now the source of truth; real reactions go through the write API, which refreshes it.
- Verification:
  - `./v3 rebuild` succeeds end-to-end (candidate validate → promote → offline snapshot); live model is schema version 27 with `viewer_tags` populated.
  - `EXPLAIN QUERY PLAN` on the lookup query: `SEARCH viewer_tags USING COVERING INDEX viewer_tags_identity_idx` — a covering index (served entirely from the index).
  - Full `./v3 test`: **1521 passed, 9 failed**. All 9 failures reproduce on the pristine baseline `3d764c7b` (two git-env `ReadModelProvenanceTest`, plus unrelated `ApprovalSeedCommandTest`, `AgentIdentityServiceTest`, `PlatformDocsStaticTest`, `ReadModelBuilderTimingTest`, two `LocalAppSmokeTest`, `WriteApiSmokeTest::testHistoricalReactionSignatures`) — none introduced by this cycle. Every viewer-tag test this cycle touched passes.
- Stage work: original 0.10; retrospective ≈0.22 — over, because the incremental-path gap (not foreseen at Step 3) required a new refresh method and the baseline comparison to separate my regressions from pre-existing failures.

## Completion Contract — result

- Normal entry / end-to-end outcome: **met.** Logged-in viewer like/flag/vote/label state renders byte-identical (proven by the oracle parity test), answered by the indexed `viewer_tags` query with no `records/*` globbing, and served from ≤1 query per (family, identity) per connection (query-count test).
- Required recovery: **met.** A pre-27 model rebuilds on normal startup via `schema_identity_mismatch` before the viewer-tag query runs (Stage 5); no-reaction/anonymous/empty cases behave as before (parity test); null-identity and parse-error records are skipped as before.
- Deployment/external verification: **met.** `./v3 rebuild` succeeds; `EXPLAIN QUERY PLAN` confirms covering-index use; incremental write path keeps `viewer_tags` current (Stage 6).
- Release condition: **met.** Schema-change checklist complete (version 26→27, spec updated, prior-version rebuild regression through startup); affected focused tests green; full-suite delta is zero new failures.

## Follow-ups surfaced (out of scope, not done here)

Both are tracked in the shared backlog:
[`docs/plans/backlog/read_model_maintenance_followups.md`](../../backlog/read_model_maintenance_followups.md).

- `dropSchema()` hand-maintains its table list; it should derive the drop set from `ReadModelSchema::statements()` so new tables cannot be forgotten. This cycle hit that fragility.
- The broader removal of `inactive`/`excludedPublicationPosts` exclusions from the thread-label derivation (scores/labels/votes), hinted by the user's "we're getting rid of them," is a separate cross-cutting change.

## Retrospective scope assessment (study closeout)

- Combined scope v1 delivered across six stages on one branch; no split taken despite the 1.15 over-threshold total. Two findings beyond the Step 3 plan — the `dropSchema` coupling (S2) and the incremental-path projection gap (S6) — were absorbed within their stages rather than triggering a split; both were latent gaps the plan's verification steps surfaced, not scope creep.
- Retrospective whole-cycle assessment: **≈1.2**, marginally above the 1.15 projection, driven by the incremental-path gap and the object-id-reuse fix that the plan did not anticipate. Independently judged, not the stage-work sum. Single exposed, non-blind rater, UTC 2026-10-24.
- Verification performed: schema/metadata unit tests, projection==file-scan oracle parity, query-count memo proof, prior-version startup rebuild regression, `./v3 rebuild` + `EXPLAIN QUERY PLAN`, and a full `./v3 test` baseline-differenced run. Omissions: no browser/UI screenshot checks (server-rendered viewer-tag markup is asserted in the smoke tests; no presentation change).
- Maintainer comfortable-fit judgment: **pending** (not yet reviewed/merged). Technical completion and study-outcome acceptance are distinct.
