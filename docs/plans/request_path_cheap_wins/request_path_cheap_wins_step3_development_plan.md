> **Feature plan:** [Step 1](./request_path_cheap_wins_step1_solution_assessment.md) · [Step 2](./request_path_cheap_wins_step2_feature_description.md) · [Step 3](./request_path_cheap_wins_step3_development_plan.md) · [Step 4](./request_path_cheap_wins_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-25T17:17:37-04:00 — scope v1 (explicit "Approved Step 3" in session)

Effort study: `railspike-effort` / `v3/request_path_cheap_wins`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled (no per-stage stage-work values).

## Completion Contract

- Normal entry: any dynamic request through `FrontController`/`Application` — a page render that references assets; a board/listing render on a meta-tagged instance; a docs/static-artifact request.
- End-to-end outcome: byte-identical output, headers, and gate decisions, produced with one transaction for the Fastmod meta insert, one `FeatureFlagEvaluator` and one static-release-root resolution per request, and each asset file hashed at most once per request.
- Required recovery: unchanged — empty/disabled Fastmod meta returns `$read` untouched; a missing/invalid asset falls through to the same non-fingerprinted path; a missing `current` release still yields `null`.
- Release condition: affected focused suites pass; a same-output regression check shows no rendered-output/header change; full-suite delta zero new failures vs. baseline; no schema or record-format change.

## Key Risks

- **#4 nested transaction** — `decorate()` must not `beginTransaction()` inside an open one. *Validated in planning:* `decorate()` is reached only via `RouteServices::pdo()` on a freshly opened read connection; no `beginTransaction` exists on that path. Mitigation retained: guard with `inTransaction()` and wrap only the insert loop.
- **#5a evaluator reuse** — one cached evaluator must not stale a later flag check. Flags resolve from per-request-immutable inputs (records/env); build once after the config-error gate, before first use.
- **#5b release-root freshness** — the cached `realpath(current)` must be request-scoped and must not skip an intended `clearstatcache`. Resolve once on first need within `handle()`; do not persist across requests.
- **#6 hash memo** — per-path static memo must be keyed by resolved absolute path and behave under the per-request-process model; must not change fingerprinted URLs or build-time copy behavior (each copy reads a source once).

## Stage 1 — #4 Fastmod meta insert transaction

**Estimate: n/a (extended estimates off)**

- Goal: wrap the `fastmod_public_meta` insert loop in a single guarded transaction; decorated `threads` view identical.
- Dependencies: none.
- Expected changes: in `Scoring/FastmodMetaRead::decorate()`, around `foreach($ids as $id) $q->execute([$id])`, begin a transaction only if `!$read->inTransaction()`, commit after the loop, and roll back on exception before rethrowing. No change to the empty-id / disabled early returns or the view DDL.
- Verification: `FastmodMetaTest` + `FastmodTaggingFlagTest` pass unchanged (decorated view yields same rows and `meta` labeling); add/extend an assertion that the active-id set is inserted (same resulting temp-table contents) under the transaction. Manual: decorate with a multi-id fixture and confirm identical `threads` projection vs. pre-change.
- Canonical components touched: `src/ForumRewrite/Scoring/FastmodMetaRead.php`; `tests/FastmodMetaTest.php` (assertion if needed).

## Stage 2 — #5 FrontController evaluator + release-root reuse

**Estimate: n/a**

- Goal: build one `FeatureFlagEvaluator` per `handle()` and resolve the static-release root at most once per request.
- Dependencies: none (independent of Stage 1).
- Expected changes: in `Host/FrontController::handle()`, construct the evaluator once (after `configurationError()` returns null, before the `APPROVED_MEMBERS_ONLY` check) and pass/reuse it for the later `IMAGE_CONTENT_ENABLED` check in the static-artifact branch. Add a request-scoped memo for `activeStaticReleaseRoot()` (nullable-cached within the `FrontController` instance, which lives one request) so repeated `firstExistingPath()`/`sendStaticDocsResource()` calls reuse one `clearstatcache`+`realpath`. Preserve exact return values (including the `null` when `current` is absent).
- Verification: `FeatureFlagEvaluatorTest`, `FeatureFlagsBehaviorTest` pass; `LocalAppSmokeTest` front-controller/static-artifact/docs cases pass unchanged (members-only, image-content toggle, static serving, docs resource). Add a focused assertion (or a counting probe) that one `handle()` resolves the release root at most once and builds one evaluator. Confirm the memo does not leak across requests (new `FrontController` per request).
- Canonical components touched: `src/ForumRewrite/Host/FrontController.php`.

## Stage 3 — #6 AssetFingerprint per-request hash memo

**Estimate: n/a**

- Goal: memoize `assetHash()` per resolved absolute path so each asset is hashed at most once per request; URLs/resolution/replacement byte-identical.
- Dependencies: none (independent).
- Expected changes: add a `private static array $hashCache = []` to `Host/AssetFingerprint` keyed by absolute path; `assetHash()` returns the cached value if present, else computes `hash_file` + stores. Add a `resetHashCache()` for tests/long-lived processes (parallel to `ViewerTagLookup::resetMemo()`). All callers (`fingerprintedPath`, `sourcePathForFingerprint`, `replacementPathForFingerprint`, build-time copy helpers) inherit the memo. Confirm build-time copy correctness: each source is read once per copy; memoization by path is safe because a source file is not rewritten mid-process during a copy.
- Verification: new `AssetFingerprintTest` — asserts (a) `fingerprintedPath`/source/replacement return identical values with the memo as without (byte-identical), and (b) a hash-count probe (temporarily instrument or compare file mtime/access can't be relied on — instead assert via repeated calls that the same path hashed once by swapping in a counting wrapper, or by asserting the cache holds one entry after N references). `ProfileThemePresentationTest`, `DocsPublicationTest`, `LocalAppSmokeTest` asset paths unchanged.
- Canonical components touched: `src/ForumRewrite/Host/AssetFingerprint.php`; new `tests/AssetFingerprintTest.php`; `tests/run.php` registration.

## Stage 4 — Verification / same-output regression + full suite

**Estimate: n/a**

- Goal: confirm the batch is behavior-neutral end to end.
- Dependencies: Stages 1–3.
- Expected changes: none to source; verification only, recorded in Step 4.
- Verification: run the affected focused suites together (Fastmod meta/tagging, feature-flag, front-controller/static smoke, asset/theme/docs). Capture a same-output check: a representative page render + an RSS render + a static-artifact serve produce identical bytes/headers before vs. after. Run full `./v3 test` and baseline-difference the failure set against current `main` so any new failure is attributable. Report pass/fail/inapplicable per stage.
- Canonical components touched: none (observation only).

## Effort snapshot and approval

- Step 3 handoff projected total: **≈0.40 reaffirmed** (unchanged). Three independent, behavior-neutral stages plus shared verification; the #6 investigation gate is resolved (real memo). The transaction-nesting risk was checked during planning and found not to apply on the live path. Single exposed, non-blind rater; UTC 2026-10-25; not measured effort, an approval, or a completed outcome.
- Stages are independent and may land in any order; Stage 4 depends on all three. No split anticipated (well under one day).
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping. Planning writes remain uncommitted until the implementing commits.
- Await `Approved Step 3` (recorded in this document) before creating the feature branch, the planning commit, or implementing.
