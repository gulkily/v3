> **Feature plan:** [Step 1](./request_path_cheap_wins_step1_solution_assessment.md) · [Step 2](./request_path_cheap_wins_step2_feature_description.md) · [Step 3](./request_path_cheap_wins_step3_development_plan.md) · [Step 4](./request_path_cheap_wins_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-25T17:16:00-04:00 — scope v1 (explicit "Approved Step 2" in session)

Effort study: `railspike-effort` / `v3/request_path_cheap_wins`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled.

## Feature

The three Option-A cheap wins delivered as one behavior-neutral slice: a request
through the dynamic read path produces byte-identical output, headers, and gate
decisions while doing measurably less redundant per-request work — one
transaction for the Fastmod meta insert, one feature-flag evaluator and one
static-release-root resolution per request, and each asset file hashed at most
once per request.

Scope is exactly punch-list items #4, #5, #6 (`performance_low_hanging_fruit_v1.md`).
No schema change, no rendered-output change. The larger board SQL rewrite (item
#3) and the already-shipped items #1/#2 are out of scope.

## Problem (grounded in current code)

- **#4 — Fastmod meta insert, un-transacted** (`Scoring/FastmodMetaRead::decorate()`):
  the active-id loop is `foreach ($ids as $id) $q->execute([$id])` into
  `temp.fastmod_public_meta` with no surrounding transaction, so N meta ids cost N
  implicit commits on every decorated read-model connection (meta-tagged instances).
- **#5 — FrontController per-request redundancy** (`Host/FrontController::handle()`):
  `FeatureFlagEvaluator::forApplication(...)` is constructed up to **three** times
  in one request (for `APPROVED_MEMBERS_ONLY`, and again for `IMAGE_CONTENT_ENABLED`
  in the static-artifact branch); `activeStaticReleaseRoot()` runs
  `clearstatcache(true, …)` + `realpath()` on **every** call, reached from
  `sendStaticDocsResource()` and from each `firstExistingPath()` invocation.
- **#6 — Asset hashing, not memoized** (`Host/AssetFingerprint::assetHash()`):
  calls `hash_file('sha256', $path)` with no cache; `fingerprintedPath()` runs per
  asset reference during rendering, so the same file is re-hashed from disk
  repeatedly within one request. **Investigation gate resolved by reading the code:
  not memoized today → a real per-request memo, not a no-op.**

## User stories

- As any visitor loading a page, I want the server to avoid re-hashing the same
  asset and rebuilding the same request-scoped state repeatedly, so responses stay
  fast with no change to what I receive.
- As an operator of a meta-tagged instance, I want board/listing decoration to
  insert its active-id set in one transaction rather than N, so warm reads do less I/O.
- As a maintainer, I want these to be provably behavior-neutral, so they carry low
  review risk and no schema or output change.

## Core requirements

- **#4:** wrap the `fastmod_public_meta` insert loop in a single explicit
  transaction (guarded so it never nests inside a caller transaction). The decorated
  `threads` temp view is identical (same rows, same `meta` label insertion). The
  empty-id and meta-disabled paths still return `$read` unchanged.
- **#5a:** build one `FeatureFlagEvaluator` per `handle()` call (after the
  configuration-error gate, before first use) and reuse it for every flag check;
  `APPROVED_MEMBERS_ONLY` and `IMAGE_CONTENT_ENABLED` decisions are unchanged.
- **#5b:** resolve the active static-release root at most once per request and reuse
  it across `sendStaticDocsResource()` and `firstExistingPath()`; the value still
  reflects the `current` symlink for this request (freshness semantics preserved).
- **#6:** memoize `assetHash()` per request, keyed by resolved absolute file path,
  so each asset is hashed at most once per request. Fingerprinted URLs, source-path
  resolution, and stale-asset replacement are byte-identical.
- **Invariant across all three:** no schema change; no change to rendered HTML, RSS,
  response headers, asset URLs, gate decisions, or error behavior.

## Delivery scope

- Work type: **application change** — three localized read-path optimizations.
- Not documentation-only. No read-model schema change.

## Completion boundary

- Normal entry: any dynamic request through `FrontController`/`Application` — a page
  render that references assets; a board/listing render on a meta-tagged instance; a
  docs/static-artifact request.
- End-to-end outcome: identical output, headers, and gate decisions, produced with
  (a) one transaction for the Fastmod insert, (b) one flag evaluator and one
  static-release-root resolution per request, (c) each asset hashed at most once per
  request.
- Recovery: unchanged — empty/disabled Fastmod meta returns `$read` untouched; a
  missing/invalid asset falls through to the same non-fingerprinted path; a missing
  `current` release still yields `null` as today.
- Release condition: affected focused suites (Fastmod meta, feature-flag/front-
  controller, asset/template) pass; a same-output regression check confirms no
  rendered-output or header change; full-suite delta zero new failures vs. baseline;
  no schema or record-format change.

## Known user or environment

No specific end user, machine, or environment named; omitted.

## Risks

- **#4 nested transaction:** if `decorate()` could run inside a caller's open
  transaction, `beginTransaction()` would throw. Earliest validation: confirm the
  decoration path owns its connection / is not already in a transaction. Mitigation:
  guard with `inTransaction()` and scope the transaction tightly around only the
  insert loop.
- **#5a evaluator reuse vs. mid-request flag change:** caching one evaluator must not
  stale a later check. Earliest validation: confirm flags resolve from immutable
  per-request inputs (records/env) with no mid-request writes. Mitigation: build once,
  after the config gate, before first use.
- **#5b release-root caching vs. freshness:** the cached `realpath(current)` must not
  outlive the request or skip an intended `clearstatcache`. Earliest validation:
  confirm the memo is bounded to one `handle()` call. Mitigation: request-scoped memo,
  resolved on first need.
- **#6 hash-memo staleness:** a per-request cache must not serve a stale hash if an
  asset changes mid-request (it does not in normal serving; build-time copy reads each
  source once). Earliest validation: cache keyed by absolute path, lifetime bounded to
  the request/process. Mitigation: per-path static memo, consistent with the
  per-request-process model used elsewhere (e.g. `ReactionSignature`).

## Shared component inventory

- `Scoring/FastmodMetaRead.php` (`decorate()`) — #4; sole owner of the temp-table
  insert. Edited in place; returns the same decorated PDO.
- `Host/FrontController.php` (`handle`, `activeStaticReleaseRoot`, `firstExistingPath`,
  `sendStaticDocsResource`) — #5; evaluator reuse + release-root memo. No new surface.
- `Host/AssetFingerprint.php` (`assetHash`) — #6; add a private static per-path hash
  memo used by all callers (`fingerprintedPath`, `sourcePathForFingerprint`,
  `replacementPathForFingerprint`, and the build-time copy helpers). Note: the
  punch-list referenced `View/AssetFingerprint.php`; the class actually lives at
  `Host/AssetFingerprint.php` (and there is no separate `TemplateRenderer::assetPath`
  memo today).
- No user-facing component, route, or API surface changes.

## Simple user flow

1. A request enters `FrontController::handle()`.
2. One `FeatureFlagEvaluator` is built and reused for all flag checks; the static-
   release root is resolved once when first needed.
3. If the page renders through the app and references assets, each distinct asset file
   is hashed once; repeat references reuse the cached hash.
4. On a meta-tagged instance, board/listing decoration inserts its active-id set in a
   single transaction.
5. The response — HTML, headers, asset URLs, gate decisions — is identical to before.

## Success criteria

- `FastmodMetaRead::decorate()` performs the id inserts within a single transaction;
  the decorated `threads` view yields identical rows and `meta` labeling (Fastmod meta
  tests pass unchanged).
- `FrontController` builds at most one `FeatureFlagEvaluator` and performs at most one
  static-release-root `realpath` resolution per request, with unchanged members-only /
  image-content / static-artifact / docs decisions (front-controller/feature-flag
  tests pass unchanged).
- `AssetFingerprint::assetHash()` hashes each file at most once per request;
  fingerprinted URLs, source resolution, and stale replacement are byte-identical
  (asset/template tests pass unchanged).
- No schema change, no rendered-output or header change; affected focused suites green;
  full-suite delta zero new failures vs. baseline.

## Effort snapshot and approval

- Cycle `v3/request_path_cheap_wins`, scope v1; anticipated starting revision: current
  `main` at this cycle's branch time; reference R1 / protocol P2; single exposed,
  non-blind rater; UTC 2026-10-25. Extended estimates not enabled.
- Step 2 handoff projected total: **≈0.40 reaffirmed** (unchanged from Step 1).
  Resolving the #6 investigation gate (not memoized → real memo) removed the main Step
  1 uncertainty without adding scope; the three changes remain individually trivial and
  behavior-neutral. Single exposed, non-blind rater; not measured effort, an approval,
  or a completed outcome.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping. Planning
  writes remain uncommitted.
- Await `Approved Step 2` (recorded in this document) before Step 3; requirements may be
  revised without advancing.
