> **Feature plan:** [Step 1](./deploy_header_prompt_step1_solution_assessment.md) · [Step 2](./deploy_header_prompt_step2_feature_description.md) · [Step 3](./deploy_header_prompt_step3_development_plan.md) · [Step 4](./deploy_header_prompt_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/deploy_header_prompt` ([ledger](../effort_estimation_trial.md), standing enrollment under protocol P2)
Extended estimates: enabled

Cycle estimate: 0.24

# Step 4: Implementation Summary — Deploy prompt in the page header

Branch: `feat/deploy-header-prompt`. Planning commit: `886d81ee`.

Step 4 entry projection: 0.24, reaffirmed unchanged from Step 3 (Claude, 2026-11-10 UTC, exposure: Step 3 score seen). Stage estimates below are copied from Step 3, not new ratings.

## Stage 1 - Flag and check interval

**Original estimate: 0.06**
**Retrospective stage work: 0.05**

- Changes:
  - `FeatureFlagRegistry.php`: new `FORUM_DEPLOY_HEADER_PROMPT_ENABLED` ("Deploy prompt"), off by default, site mutable, EXPERIENCE group after Unread.
  - `AppUpdate.php`: `check()` takes an optional maximum age. A fetch newer than that returns outcome `checked`, reason `recent`, without contacting GitHub. The time comes from `FETCH_HEAD`, so no new state is stored.
  - `DeployController.php`: `check` reads an optional `max_age`, clamped to 60–86400 seconds. Without it, Check for updates still always fetches.
- Verification:
  - Passed: `./v3 test --smoke AppUpdateTest DeployPageTest FeatureFlagEvaluatorTest`, 480/480.
  - New tests: a recent fetch is reused; the forced check still fetches; an hour-old fetch is refreshed.
  - Not run: full suite (maintainer runs it).
  - Not covered: the `max_age` clamp through the HTTP endpoint; Stage 2's browser test exercises that path.
- Notes:
  - Reuse: the existing `check` action and lock; no new endpoint.
  - Workspace note: the shared workspace dropped untracked plan files once before the planning commit. The plans were rewritten from their approved text; the Step 1 debug entry is restored in this commit with a revision note.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Stage 2 - Remember the operator and reveal the button

**Original estimate: 0.07**
**Retrospective stage work: 0.09**

- Changes:
  - `deploy_control.js`: remembers this browser (`forum_deploy_prompt` in local storage) after Deploy now ends `deployed` or `up_to_date`, or a deploy started from the page finishes `deployed`. Check for updates alone does not.
  - New `deploy_prompt.js`: in a remembered browser, waits three seconds, sends `check` with `max_age` 600 and gives up after fifteen seconds. Reveals the button when `behind` is above zero. A 403 forgets the browser. Does nothing on Tools → Deploy.
  - `app_header.php`: a hidden Deploy now button and status line by the site name, only with the flag on. Server HTML stays the same for every viewer.
  - `TemplateRenderer.php` and `layout.php`: load the script only with the flag on and the app header.
  - `site.css`: `.deploy-prompt` styles from shared theme variables. Phones keep it on the site name's row; wider screens put it under the name.
  - `tests/Support/app_layout_fixture.php`: a fifth argument turns the flag on.
- Verification:
  - Passed: `node tests/browser/deploy_prompt.mjs`. It covers flag off (no script, no request), a never-deployed browser (no request), the three-second delay, the request body, behind and up-to-date replies, a 403 forgetting the browser, and the tab bar unchanged on phones.
  - Passed: every theme (Word 97, Chicago, Midnight, Gloss, Gloss Dark, Engagify) at 390 and 1100 wide. The button is usable, inside the header, and the destinations stay on one row. Screenshots reviewed for all twelve.
  - Passed: `./v3 test --smoke AppNavigationPlacementTest NavIconSetsTest DeployPageTest FeatureFlagEvaluatorTest`, 449/449.
  - Passed: header regressions `inbox_unread.mjs`, `nav_slots.mjs`, `viewer_nav_prepaint.mjs`; `git diff --check`.
  - Not run: full suite.
- Notes:
  - Placement refinement (in scope, under the Stage 2 crowding mitigation): the plan placed the button beside New post. On phones that slot is in the bottom tab bar, and on desktop it wrapped Tools to a second row in Chicago and Gloss. The button now sits by the site name instead and does not reuse `.new-post-button`.
  - On wider screens the destinations shift right by about 20px when the button appears in Chicago, Gloss and Engagify. This is a post-paint change for a real state change, which `AGENTS.md` allows.
  - Navigation icons: not applicable; Deploy now is an action, not a navigation destination.
  - Engagify's parody popups cover its header in screenshots; the geometry checks still pass there.
- Estimate details: original scope unchanged; the placement rework is new understanding, not changed scope.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
