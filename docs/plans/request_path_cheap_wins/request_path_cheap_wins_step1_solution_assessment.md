> **Feature plan:** [Step 1](./request_path_cheap_wins_step1_solution_assessment.md) · [Step 2](./request_path_cheap_wins_step2_feature_description.md) · [Step 3](./request_path_cheap_wins_step3_development_plan.md) · [Step 4](./request_path_cheap_wins_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-25T16:41:40-04:00 — scope v1 (explicit "Approved Step 1" in session)

Effort study: `railspike-effort` / `v3/request_path_cheap_wins`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

## Original Query

please look for the lowest hanging fruit for improving performance and build a document with a list of them. [...] please write three Step 1 documents.

## Understood Intent

Deliver the third of three Railspike cycles from the performance punch-list in [`performance_low_hanging_fruit_v1.md`](../performance_low_hanging_fruit_v1.md): a small batch of low-risk, no-schema request-path optimizations — item #4 (wrap the Fastmod temp-table insert in a transaction), item #5 (reuse one `FeatureFlagEvaluator` and resolve the static-release root once per request in `FrontController`), and item #6 (confirm whether `AssetFingerprint` memoizes hashing and add a per-request cache if not). These share one risk profile ("pure read-path optimization, behavior unchanged") and one verification story, so they are batched rather than run as three separate cycles.

## Problem

Several per-request operations do redundant work with no behavioral benefit: the Fastmod read decorator inserts active ids one row at a time without a transaction, `FrontController` rebuilds the feature-flag evaluator and re-runs `realpath`/`clearstatcache` multiple times per request, and asset fingerprints may be re-hashed per reference.

## Option A — Batch all three cheap wins in one cycle

Fix #4, #5, and #6 together: single transaction around the Fastmod insert loop, one reused flag evaluator plus one static-release-root resolution in `FrontController`, and a confirmed/added per-request fingerprint memo.

- Pros: amortizes planning/verification overhead across changes that share a risk profile; one PR, one review; each change is independently trivial and low-risk.
- Cons: #6 needs an investigation gate first (confirm `AssetFingerprint` isn't already memoizing); if it already memoizes, that stage becomes a no-op to record rather than drop silently.

## Option B — One cycle per item

Run #4, #5, and #6 as three separate Railspike cycles.

- Pros: maximal isolation per change.
- Cons: planning tax dwarfs the work (a transaction wrapper, a reused variable); three sets of near-identical verification; poor effort-to-overhead ratio for changes this small.

## Option C — Fold these into the larger board SQL-rewrite cycle (item #3)

Attach these tweaks to the item #3 query-rewrite work.

- Pros: fewer total cycles.
- Cons: mixes trivial, zero-behavior tweaks with a genuinely behavioral SQL rewrite — different risk profile and verification needs; harder to review and to isolate any regression.

## Recommendation

**Option A.** Releasable vertical slice: a request through the dynamic read path produces identical output with less redundant per-request work, verified by focused checks plus an unchanged-behavior regression pass. Sequence as independent stages: (1) Fastmod insert transaction, (2) `FrontController` evaluator/root reuse, (3) `AssetFingerprint` investigate-then-memoize (record a no-op if already cached). Each is well under two hours. Keep the larger board SQL rewrite (item #3) out of this batch; reject Option C's coupling.

Verify per stage: Fastmod meta decoration still yields the same decorated `threads` view (meta label present/absent correctly); `FrontController` gate decisions (members-only, image-content, static-artifact serving) unchanged; asset URLs/fingerprints unchanged while files are re-hashed at most once per request. Run the affected focused suites (Fastmod, feature-flag/front-controller, asset/template) and report pass/fail/inapplicable per stage.

## Follow ups and scope

In scope: items #4, #5, #6 only, with no schema change and no change to rendered output. Out of scope and tracked separately: the read-model index cycle (item #2), viewer-tag memoization (item #1), and the board/search/RSS SQL-level LIMIT/sort/search rewrite (item #3). If #6's investigation finds a deeper fingerprinting issue beyond a per-request memo, that becomes a separate scoped request rather than expanding this cycle.

## Effort snapshots and approval

- Cycle `v3/request_path_cheap_wins`, scope v1; starting revision `8d51a177` (current `main` after the punch-list commit); reference R1 / protocol P2; single exposed rater; UTC 2026-10-24.
- Intake: recorded after reading `FastmodMetaRead.php` and tracing `FrontController`/`RouteServices`; `AssetFingerprint` memoization is unconfirmed (investigation gate in Stage 3). Prior exposure to the punch-list disclosed; no pristine pre-inspection estimate claimed.
- Step 1 handoff projected total (Option A, against recovered R1): **≈0.40** — three individually trivial, zero-behavior changes plus one investigation gate and shared read-path verification; near the "score emphasis" (0.30)–"toolbar identity" (0.35) R1 band, slightly higher for spanning three independent stages. Single exposed, non-blind rater; not measured effort, an approval, or a completed outcome.
- Documentation boundary: this cycle's plans/debug log, later index organization, and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; recommendation may be revised without advancing.
