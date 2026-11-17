> **Feature plan:** [Step 1](./asset_bypass_step1_solution_assessment.md) · [Step 2](./asset_bypass_step2_feature_description.md) · [Step 3](./asset_bypass_step3_development_plan.md) · [Step 4](./asset_bypass_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/asset_bypass`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.4**

Step 3 approved 2026-11-16 23:22 ET ("approved step 3"). Branch `feat/asset_bypass`, planning commit `fb90541`. At Step 4 entry, 2026-11-17 UTC, Claude reaffirms the Step 3 projection of 0.4 unchanged; same single exposed rater.

Original stage work, copied from Step 3: Stage 1 0.08 · Stage 2 0.1 · Stage 3 0.12 · Stage 4 0.08.

## Test environment

- I installed Apache 2.4.58 and mod_php 8.3 in my workspace; they are not a project dependency. Apache refuses to run as root, so `tests/high_traffic_apache_test.py` runs as an unprivileged user on a copy of the checkout.
- Baseline at `fb90541`: the smoke set plus `WebServerRoutingTest`, `HighTrafficTest`, `HighTrafficAutomationTest` and `AppUpdateTest` gave 518 run, 518 passed. The Apache test failed at its first busy-page check, which expects `Retry-After: 5`. The busy page sends 30 on purpose (`FrontController::PAGE_RETRY_NOSCRIPT_SECONDS`). Stage 1 fixes that test, as part of this work.

## Stage 1 - Plain presentation files bypass PHP

**Original estimate: 0.08**

**Retrospective stage work: 0.1**

- Changes:
  - `public/.htaccess` line 35 now matches files directly in `assets/` with an allowlisted extension (css, js, ico, png, jpg, jpeg, gif, svg, webp, woff, woff2), plus `/favicon.ico`.
  - A root `<If>` block gives those files PHP's headers: `max-age=300, must-revalidate` and `nosniff`.
  - `WebServerRoutingTest`: the assertion now names the new condition. A new case checks the condition admits and rejects the right paths.
  - `tests/high_traffic_apache_test.py`: new checks for plain files (no PHP request, headers, HEAD). HTML, a `.map` and an unknown fingerprint still reach PHP.
  - Stale expectations fixed: `Retry-After` is now 30. The separate prepared read model is gone since `43e0e5bb` (2026-11-10), so the prepared-copy checks are removed. The live-model check stays.
- Verification:
  - Passed: `./v3 test --smoke WebServerRoutingTest` gave 469 of 470 passed. The one failure, `StatusCommandTest::testStatusPrintsAlignedHealthSummaryAndEveryFix`, comes from the uncommitted `public/.htaccess` edit: `./v3 status` adds a `public/.htaccess has local edits` row without the label column. It passes once committed (see Stage 2).
  - Passed: the Apache test's new Stage 1 checks.
  - Blocked: the Apache test stops later, at the import-withdrawal check. That check also fails at `fb90541`; see the pending decision below.
  - Full suite not run.
- Notes:
  - Key Risk 1 is resolved, but in the opposite direction from the plan. A throwaway Apache 2.4.58 shows that a `.htaccess` in `assets/` with only `Header` lines leaves the root rewrites in place. A missing file still reached `index.php`. The root `<If>` is kept anyway, so no new file is needed.
  - Pending decision (pre-existing failure, outside this cycle's code): two Apache-test checks broke with the 2026-10-25 retraction simplification (`e31acf0d`). (a) Import no longer withdraws high-traffic serving. Its marker path `records/post-retractions/import.txt` stopped matching once `post-retractions/` left the withdrawal list. (b) A cached thread page shows a reply whose source was deleted and retracted while the read model was stale. Each fix is a product choice under the [retraction handoff](../retraction_simplification_handoff.md), so I am asking the maintainer.
  - Estimate details: original scope unchanged; the retrospective is higher because of the stale-test triage and Apache setup.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

## Stage 2 - The mode manages an asset link

**Original estimate: 0.1**

**Retrospective stage work: 0.08**

- Changes:
  - `HighTrafficMode::ASSET_LINK` (`.high-traffic-assets`) and a private `exposeAssets()`. It writes the cache's `assets/.htaccess` and creates the link.
    - `assets/.htaccess`: no listings; PHP's types for the allowlist, plus UTF-8 for css and js; one-year immutable, `nosniff` and `X-V3-High-Traffic: hit`.
  - `change()`: a fresh turn-on exposes the assets after the pages link, and a failure detaches both. Turn-on while already on refreshes the saved `normal.htaccess` and exposes missing assets.
  - `HighTrafficSafety::detach()` removes both links, so off, every withdrawal and `restore()` cover the asset link without their own code.
  - `.gitignore`: `public/.high-traffic-assets`.
  - `HighTrafficAutomationTest::testAssetLinkIsCreatedAndRemovedWithTheMode`: covers turn-on, turn-on while on (link added, saved rules refreshed), off, withdrawal, `restore`, and a refused regular file at the link path.
- Verification:
  - Passed: `./v3 test --smoke HighTrafficAutomationTest HighTrafficTest WebServerRoutingTest` gave 488 run, 488 passed. `StatusCommandTest` passes again now that Stage 1 is committed.
  - Passed: in the Apache test, turning the mode on created both links. The run then stopped at the same pre-existing import check.
  - Full suite not run.
- Notes:
  - Reuse: `pages/.htaccess` and its symlink pattern, and the single `detach()` used by every withdrawal path. No new service.
  - A regular file at either link path blocks turn-off and withdrawal with "must be a managed symlink". `.high-traffic` already behaves that way.
  - Estimate details: original scope unchanged.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

## Stage 3 - Apache serves the asset copies

**Original estimate: 0.12**

**Retrospective stage work: 0.14**

- Changes:
  - New rule in `HighTrafficMode::routingRules()` and the shipped block in `public/.htaccess`. It matches GET/HEAD with no query and no `Authorization`, a 12-hex name and an allowlisted extension, with no `..`. When the copy exists under `.high-traffic-assets/assets/`, the request is served from there (END). A miss falls through to PHP.
  - The refusal rule now covers `/.high-traffic-assets` as well as `/.high-traffic`.
  - `tests/high_traffic_apache_test.py`: new checks.
    - Every allowlisted fingerprinted asset on the warm `/about/` page, plus a hand-written copy, is served with zero PHP requests. Each carries `X-V3-High-Traffic: hit`, the one-year immutable header, `nosniff` and the exact PHP content type. HEAD returns no body.
    - A query string, `Authorization`, a missing copy, `restore`, turn-off and the private-instance withdrawal all reach PHP. Direct requests to the storage path return 403.
- Verification:
  - Passed: the Apache test's Stage 3 checks, up to the import check, which fails as described in Stage 1.
  - Passed: a scratch copy (not committed) skipped only the pre-existing failing checks and ran to the QDB section. It found nothing caused by this branch.
  - Failed at `fb90541`, pre-existing: on the base, with the same skips, the absent-target section's `live_hash` check fails. The QDB section's first check also fails: a `SetEnv FORUM_SITE_ID qdb` added to `.htaccess` does not reach PHP on the ordinary page in my workspace. These are recorded with the pending decision; I made no fix.
  - Passed: `./v3 test --smoke WebServerRoutingTest HighTrafficTest HighTrafficAutomationTest` (see the Stage 4 rerun).
  - Full suite not run.
- Notes:
  - Key Risk 3 is mitigated: the types are fixed in the cache folder's `.htaccess`. This host's `mime.types` would have sent `text/javascript` and `image/vnd.microsoft.icon`.
  - Estimate details: original scope unchanged; extra base-commit runs to attribute the failures.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
