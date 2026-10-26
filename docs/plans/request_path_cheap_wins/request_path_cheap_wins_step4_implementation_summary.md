> **Feature plan:** [Step 1](./request_path_cheap_wins_step1_solution_assessment.md) · [Step 2](./request_path_cheap_wins_step2_feature_description.md) · [Step 3](./request_path_cheap_wins_step3_development_plan.md) · [Step 4](./request_path_cheap_wins_step4_implementation_summary.md)

Debug mode: enabled

Approval: N/A — Step 4 is the implementation record; Steps 1–3 are APPROVED for scope v1 (recorded in their documents).

Effort study: `railspike-effort` / `v3/request_path_cheap_wins`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled.

# Step 4 — Implementation Summary

Feature branch: `feat/request-path-cheap-wins`. Planning commit: `4e61ee25`.

## Environment note

- PHP runs natively in this sandbox; `pdo_sqlite` + `mbstring` present.

## Stage 1 — #4 Fastmod meta insert transaction (`acaa1acf`)
- Changes: `Scoring/FastmodMetaRead::decorate()` wraps the `fastmod_public_meta`
  insert loop in a single transaction, guarded by `!$read->inTransaction()` (the
  path runs on a freshly opened read connection via `RouteServices::pdo()`, so it
  always owns the transaction), with rollback+rethrow on error. Empty-id /
  disabled early returns and the view DDL unchanged.
- Verification: `FastmodMetaTest` + `FastmodTaggingFlagTest` 9/9 pass — decorated
  `threads` view and `meta` labeling identical.

## Stage 2 — #5 FrontController evaluator + release-root reuse (`f0999228`)
- Changes: `Host/FrontController::handle()` builds one `FeatureFlagEvaluator`
  (was up to three per request) and reuses it for `APPROVED_MEMBERS_ONLY` and
  `IMAGE_CONTENT_ENABLED`. Added a request-scoped memo
  (`activeStaticReleaseRootMemo` + resolved flag) so `activeStaticReleaseRoot()`
  runs `clearstatcache`+`realpath` at most once per request; the controller lives
  one request, so the memo is naturally request-scoped. Return values (including
  `null` when `current` is absent) unchanged.
- Verification: `FeatureFlagEvaluatorTest`, `FeatureFlagsBehaviorTest`, and the
  `LocalAppSmokeTest` front-controller/static-artifact/docs cases pass; gate
  decisions unchanged.

## Stage 3 — #6 AssetFingerprint per-request hash memo (`b1e01c1e`)
- Changes: `Host/AssetFingerprint::assetHash()` memoizes by absolute path (a
  `private static array $hashCache`); added `resetHashCache()` (parallels
  `ViewerTagLookup::resetMemo`). All callers inherit the memo. Confirmed not
  memoized before (the #6 investigation gate). Build-time copy reads each source
  once, so path keying is safe there.
- Verification: new `AssetFingerprintTest` proves memoization behaviorally — the
  fingerprint is unchanged after a mid-process file-content change (cache reused,
  i.e. no re-hash) and refreshes after `resetHashCache()`; URLs are the expected
  `/assets/name.<12hex>.ext` form; non-asset and missing paths unchanged.
  `ProfileThemePresentationTest`, `DocsPublicationTest` asset paths unchanged.

## Stage 4 — verification / same-output regression (no source change)
- Affected focused suites together: `FastmodMetaTest`, `FastmodTaggingFlagTest`,
  `FeatureFlagEvaluatorTest`, `FeatureFlagsBehaviorTest`, `AssetFingerprintTest`,
  `ProfileThemePresentationTest`, `DocsPublicationTest` — **43/43 pass**.
- Same-output spot check: a real public asset resolves to a stable fingerprinted
  URL across repeated calls (`/assets/site.02c23f6de94c.css`), confirming URLs are
  byte-identical with the memo.
- Full `./v3 test`: **1523 passed, 9 failed.** The 9 are exactly the pre-existing
  baseline set (two git-env `ReadModelProvenanceTest`, `AgentIdentityServiceTest`,
  `ApprovalSeedCommandTest`, `PlatformDocsStaticTest`, `ReadModelBuilderTimingTest`,
  two `LocalAppSmokeTest`, `WriteApiSmokeTest::testHistoricalReactionSignatures`) —
  **zero new failures** from this cycle. (Note: the runner's summary also prints
  "fixed N runs ago" history lines for the viewer-tag `WriteApiSmokeTest` cases; a
  naive grep over the summary mis-reads those as failures. The actual `] FAIL `
  lines are the 9 above.)

## Completion Contract — result

- Normal entry / end-to-end outcome: **met.** Dynamic requests produce identical
  output/headers/gate decisions with one Fastmod transaction, one evaluator + one
  release-root resolution per request, and each asset hashed at most once per
  request.
- Required recovery: **met.** Empty/disabled Fastmod meta returns `$read`
  unchanged; missing/invalid asset falls through to the non-fingerprinted path;
  missing `current` release still yields `null`.
- Release condition: **met.** Affected focused suites green (43/43); same-output
  check confirms stable asset URLs; full-suite delta zero new failures; no schema
  or record-format change.

## Retrospective scope assessment (study closeout)

- Scope v1 delivered across four stages; no scope change, no splits, no enhancement
  requests. The #4 transaction-nesting risk was checked during Step 3 planning and
  found not to apply on the live path; the #6 investigation gate resolved to a real
  memo (not a no-op). Starting revision `f076560b` (current `main`); branch
  `feat/request-path-cheap-wins`; planning commit `4e61ee25`; stage commits
  `acaa1acf` (S1), `f0999228` (S2), `b1e01c1e` (S3), plus this S4 summary.
- Retrospective whole-cycle assessment: **≈0.40**, matching the Step 1–3 projection.
  Single exposed, non-blind rater, UTC 2026-10-25. Not measured effort or an
  independent rating.
- Maintainer comfortable-fit judgment: **pending** (not yet reviewed/merged).
- Process note: this cycle piloted the approval-in-document convention — Steps 1–3
  each carry an `APPROVED — user — <timestamp> — scope v1` line, recorded only after
  the explicit in-session approval, with Step N+1 drafted only after Step N was
  recorded. See the debug log.
