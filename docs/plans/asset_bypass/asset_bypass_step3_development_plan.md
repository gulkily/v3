> **Feature plan:** [Step 1](./asset_bypass_step1_solution_assessment.md) · [Step 2](./asset_bypass_step2_feature_description.md) · [Step 3](./asset_bypass_step3_development_plan.md) · [Step 4](./asset_bypass_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/asset_bypass`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.4**

Step 2 approved 2026-11-16 23:17 ET ("approved step 2"). The approval covers the recommended answers to decisions 2 and 3: one-year immutable headers, and a refresh of the saved rules on every turn-on. Decision 1 (the server's local edit) is still open; see Key Risks.

## Completion Contract

- **Entry:** Tools → Operations → High-traffic mode, or `./v3 high-traffic enable`; then an ordinary page visit.
- **End-to-end:** in the isolated Apache test, a warm cached page and its fingerprinted assets add zero PHP requests.
- **Recovery:** turning off, a withdrawal or `restore` removes the asset link, and the same URLs reach PHP again. A missing copy falls through to PHP.
- **Deployment:** the deploy applies over a stand-in local edit. Afterwards, `./v3 high-traffic enable` creates the asset link even when the mode is already on.
- **Release:** the maintainer deploys to zenmemes, runs enable, and sees `X-V3-High-Traffic: hit` on an asset response.

## Key Risks

- **High risk: a `.htaccess` inside `public/assets/` could replace the root rewrite rules for that folder.** Impact: fingerprinted assets would return 404 in both modes. Early validation: Stage 1, in the Apache test. Mitigation: add no `.htaccess` under `public/assets/`. Set plain-file headers from `public/.htaccess` with an `<If>` on the path.
- **The production local edit is unknown.** Impact: if it touches the same lines, the deploy refuses and changes nothing. That outcome is safe. Early validation: Stage 4 replays a deploy over a stand-in edit. Mitigation: ask the maintainer for the edit before release. Production replay stays pending until then.
- **Wrong content type combined with `nosniff`.** Impact: the browser drops a stylesheet or script. Early validation: Stage 3 checks each allowlisted type. Mitigation: set the types explicitly with `AddType` in the cache asset folder's `.htaccess`.
- **The asset link outlives the mode.** Impact: copies are served after the mode is off. Early validation: Stage 2 tests for off, withdrawal, `restore` and turn-on while on. Mitigation: `HighTrafficSafety::detach()` removes both links.

## Stage 1

**Estimate: 0.08**

- Goal: plain presentation files bypass PHP in both modes, with PHP's current headers.
- Dependencies: install Apache and mod_php in my workspace (not a project dependency).
- Expected changes: change the line-35 condition in `public/.htaccess` to the Step 2 extension list. Add an `<If>` block for those paths that sets `max-age=300, must-revalidate` and `nosniff`. Update `WebServerRoutingTest`.
- Verification approach: `./v3 test --smoke WebServerRoutingTest`. Extend `tests/high_traffic_apache_test.py`: a plain `.css` adds no PHP request and carries the headers. `/assets/1111.html`, a `.map` file and a fingerprinted URL still reach PHP.
- Estimate details: driver: Apache's per-directory behavior; uncertainty: `<If>` combined with `Header` on this Apache.
- Risks or open questions:
  - Impact: see the first Key Risk.
  - Early warning / validation: the Apache test in this stage.
  - Mitigation: root `<If>` only.
- Canonical components/API contracts touched: `public/.htaccess` normal rules (extended); `FrontController::resolveRawPublicAssetPath()` stays as the fallback.

## Stage 2

**Estimate: 0.1**

- Goal: the mode manages a second symlink for the asset copies.
- Dependencies: Stage 1.
- Expected changes: `HighTrafficMode::change()` writes the asset folder's `.htaccess` (headers, types, no listings) and creates `public/.high-traffic-assets` pointing at the cache's `assets/` folder. Turn-on while already on creates a missing link and refreshes the saved `normal.htaccess`. `HighTrafficSafety::detach()` removes both links. `restore()` gets the same behavior through `detach()`. Add the link to `.gitignore`.
- Verification approach: new `HighTrafficTest` cases cover turn-on, turn-on while on, off, withdrawal, `restore`, and a refused non-symlink path. Run `./v3 test --smoke HighTrafficTest HighTrafficAutomationTest`.
- Estimate details: driver: four lifecycle paths; uncertainty: low.
- Risks or open questions:
  - Impact: see the fourth Key Risk.
  - Early warning / validation: the tests in this stage.
  - Mitigation: one removal helper.
- Canonical components/API contracts touched: `HighTrafficMode` (`change()`, `restore()`), `HighTrafficSafety::detach()`, the `pages/.htaccess` pattern (reused). No new service.

## Stage 3

**Estimate: 0.12**

- Goal: Apache serves the copies while the mode is on.
- Dependencies: Stage 2.
- Expected changes: add one rule to `HighTrafficMode::routingRules()` and the shipped block in `public/.htaccess`. It matches GET/HEAD with no query and no `Authorization`, a 12-hex name and an allowlisted extension, and requires the copy to exist. Extend the refusal rule to cover `/.high-traffic-assets/`.
- Verification approach: Apache test. A warm board plus its assets adds zero PHP requests, and each type has the right `Content-Type`, `nosniff`, the one-year header and the hit header. HEAD returns no body. A missing copy, a query string, off, withdrawal and `restore` reach PHP. A direct storage URL returns 403. Run `./v3 test --smoke WebServerRoutingTest HighTrafficTest`.
- Estimate details: driver: Apache test coverage; uncertainty: mod_mime behavior in the fixture.
- Risks or open questions:
  - Impact: see the third Key Risk.
  - Early warning / validation: per-type checks.
  - Mitigation: explicit `AddType`.
- Canonical components/API contracts touched: `HighTrafficMode::routingRules()` (extended); `FrontController::resolveFingerprintedAssetPath()` unchanged as the fallback.

## Stage 4

**Estimate: 0.08**

- Goal: the deploy path and the operator's documentation.
- Dependencies: Stage 3.
- Expected changes: an `AppUpdateTest` case that deploys this change over a stand-in local edit at the end of `.htaccess`. Update `docs/runbooks/high_traffic_mode.md` (asset link, re-enable after deploy, verification). Add `docs/concepts/high_traffic_mode.md`. Ledger closeout.
- Verification approach: `./v3 test --smoke AppUpdateTest`; check links; review the final diff against Step 2. Record the production replay as pending until the maintainer shares the edit and deploys.
- Estimate details: driver: deploy test fixture; uncertainty: the real local edit.
- Risks or open questions:
  - Impact: see the second Key Risk.
  - Early warning / validation: the stand-in replay.
  - Mitigation: ask before release.
- Canonical components/API contracts touched: `AppUpdate` (used unchanged), runbook, concept inventory.

## Concepts touched

- *High-traffic mode* (changed; entry proposed in Stage 4). [Application update](../../concepts/application_update.md) (used unchanged).

The full test suite is not part of this plan; the maintainer runs it.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

Debug log: [asset_bypass_debug_log.md](./asset_bypass_debug_log.md)
