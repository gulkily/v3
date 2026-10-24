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