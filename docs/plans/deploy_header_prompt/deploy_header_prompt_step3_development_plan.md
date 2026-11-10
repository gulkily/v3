> **Feature plan:** [Step 1](./deploy_header_prompt_step1_solution_assessment.md) · [Step 2](./deploy_header_prompt_step2_feature_description.md) · [Step 3](./deploy_header_prompt_step3_development_plan.md) · [Step 4](./deploy_header_prompt_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/deploy_header_prompt` ([ledger](../effort_estimation_trial.md), standing enrollment under protocol P2)
Extended estimates: enabled

Cycle estimate: 0.24

# Step 3: Development Plan — Deploy prompt in the page header

## Completion Contract

- Normal entry: any zenmemes.com page, with `FORUM_DEPLOY_HEADER_PROMPT_ENABLED` on, in a browser that has deployed from Tools → Deploy.
- End-to-end outcome: Deploy now appears in the header when the checkout is behind; tapping it deploys and reloads on the new commit.
- Recovery: a slow check gives up silently; a refused check clears the browser flag; a failed or lost deploy shows the message and links to Tools → Deploy.
- Deployment check: the maintainer deploys this branch, then confirms the flow on his iPhone with a later commit waiting.
- Release condition: the maintainer accepts the iPhone check in production. Until then, Step 4 stays blocked on acceptance.

## Key Risks

- **A fetch on every visit.** Impact: GitHub load and a held deploy lock. Early validation: Stage 1 tests show a second check inside ten minutes does not fetch. Mitigation: the server reads the time of the last fetch from `FETCH_HEAD`; no new stored state.
- **High risk: header crowding on phones.** Impact: Deploy now pushes out New post or the account menu. Early validation: Stage 2 checks all five themes at phone width before Stage 3 adds behavior. Mitigation: reuse `.new-post-button` sizing; hide the label on narrow screens if needed.
- **Duplicate deploy taps.** Impact: two deploys race. Mitigation: the button disables while a deploy runs; the existing deploy lock refuses a second one.

## Concepts touched

- Application update: changed. Update `docs/concepts/application_update.md` in Stage 4.
- Viewer session: used unchanged.

## Stage 1 — Flag and check interval

**Estimate: 0.06**

- Goal: add the site flag and let a check reuse a recent fetch.
- Dependencies: none.
- Expected changes: add `FORUM_DEPLOY_HEADER_PROMPT_ENABLED` to `FeatureFlagRegistry.php` (off, site mutable, EXPERIENCE). Give `AppUpdate::check()` an optional maximum age in seconds; a fetch newer than that returns the current state without fetching. `DeployController.php` accepts a `max_age` field on `check`, clamped to at least 60 seconds. Tools → Deploy keeps its forced check.
- Verification approach: `./v3 test --smoke AppUpdateTest DeployPageTest` plus the feature flag registry test. New tests: a recent fetch skips the fetch; an old one fetches; the page button still forces a fetch.
- Estimate details: driver: one new branch in `check()`; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `AppUpdate.php` `check()`, `DeployController.php` `submit()`, `FeatureFlagRegistry.php`.

## Stage 2 — Remember the operator and reveal the button

**Estimate: 0.07**

- Goal: after a deploy, later visits check and reveal a hidden Deploy now button.
- Dependencies: Stage 1.
- Expected changes: `deploy_control.js` stores a browser flag after a `deployed` or completed queued deploy. `app_header.php` renders a hidden Deploy now button beside New post only when the flag is on, so HTML stays the same for every viewer. A new `deploy_prompt.js`, loaded only when the flag is on, waits about three seconds after load. It then sends `check` with `max_age` 600 and aborts after fifteen seconds. It shows the button when `behind` is above zero; on 403 it clears the browser flag. `TemplateRenderer.php` passes the script path, as it does for `inbox_unread.js`.
- Verification approach: new `tests/browser/deploy_prompt.mjs` covers: flag off makes no request; no browser flag makes no request; a behind checkout reveals the button; a 403 clears the flag. Check all five themes at phone and desktop width.
- Estimate details: driver: header layout across themes; uncertainty: Engagify and Gloss header spacing.
- Risks or open questions:
  - Impact: crowded phone header.
  - Early warning / validation: screenshots in each theme before Stage 3.
  - Mitigation: reuse `.new-post-button`; drop the label below the phone breakpoint.
- Canonical components/API contracts touched: `deploy_control.js`, `app_header.php`, `.new-post-button` in `site.css`, `TemplateRenderer.php`; pattern from `version_check.js`. A new script is needed because `version_check.js` runs for every visitor.

## Stage 3 — Deploy from the header

**Estimate: 0.05**

- Goal: the header button deploys and recovers from failure.
- Dependencies: Stage 2.
- Expected changes: `deploy_prompt.js` posts `now` on tap, disables the button and shows Deploying…. On `deployed` it reloads. On a refusal or failure it shows the message with a link to Tools → Deploy. On a dropped connection or 502–504 it links to Tools → Deploy, which follows a lost reply.
- Verification approach: extend `deploy_prompt.mjs` with stubbed replies for success, failure, 503 and a dropped connection. Confirm one attempt-log line per header deploy in `DeployPageTest`.
- Estimate details: driver: four reply cases; uncertainty: low, since `deploy_control.js` already handles them.
- Risks or open questions: duplicate taps, covered in Key Risks.
- Canonical components/API contracts touched: `/tools/deploy/` `now` action and its JSON reply.

## Stage 4 — Docs and production replay

**Estimate: 0.04**

- Goal: record the feature and verify it on the maintainer's iPhone.
- Dependencies: Stage 3.
- Expected changes: update `docs/concepts/application_update.md` and `docs/runbooks/production_deploy.md`. Final diff review. Open the PR with a checkout snippet.
- Verification approach: smoke set plus changed tests; full suite not run. Production replay is pending the maintainer: turn the flag on, deploy once from Tools → Deploy, merge a later commit, open the board on the iPhone, tap Deploy now. Also replay a dropped connection by locking the phone mid-deploy.
- Estimate details: driver: production replay; uncertainty: depends on the maintainer's time.
- Risks or open questions: a desktop pass does not satisfy the iPhone condition; the item stays blocked until he confirms.
- Canonical components/API contracts touched: concept and runbook docs only.

## Study snapshot

| Field | Value |
| --- | --- |
| Study / candidate | `railspike-effort` / `v3/deploy_header_prompt` |
| Checkpoint | Step 3 handoff |
| UTC date | 2026-11-10 |
| Rater | Claude (single rater) |
| Score | 0.24 projected total |
| Scope version | v2, unchanged; starting revision `cdec8702` |
| Reference version | R1 |
| Exposure | Step 2 score 0.22 seen |
| Reason for change | Browser test file and recovery cases added; stored timestamp no longer needed (`FETCH_HEAD`), which offsets part of it |

Ledger update pending: indexed with the step 4 closeout.

Status: Approved Step 3 on 2026-11-10.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
