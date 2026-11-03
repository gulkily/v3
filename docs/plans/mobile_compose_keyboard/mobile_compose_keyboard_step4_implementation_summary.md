> **Feature plan:** [Step 1](./mobile_compose_keyboard_step1_solution_assessment.md) · [Step 2](./mobile_compose_keyboard_step2_feature_description.md) · [Step 3](./mobile_compose_keyboard_step3_development_plan.md) · [Step 4](./mobile_compose_keyboard_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `v3/mobile_compose_keyboard` · [ledger](../effort_estimation_trial.md#cycle-v3mobile_compose_keyboard) (standing P2 enrollment)

Extended estimates: enabled

**Cycle estimate: 0.40**

Branch `feat/mobile-compose-keyboard` from `main` `fd7e525df`; planning commit `e55a06a09` (Steps 1–3 moved into this folder, standard navigation added, ledger and source-record paths repaired, plan index entry). No debug log existed for this feature before Step 4.

## Step 4 entry checkpoint

- 2026-11-03: the user responded "approved step 3". The Step 2 and Step 3 artifacts had recorded that a separate Step 2 approval was not recovered from the imported thread; this Step 3 approval by the maintainer is taken as approval of the plan chain as revised (v1.1), including the Q1–Q3 defaults (collapsed tools hide while docked; title stays; paned dialog gets the order only).
- Whole-cycle projection **0.40 reaffirmed** for scope v1.1 before implementation. Rater: Claude (claude-fable-5-1), single exposed rater: saw the historical 0.40 Step 2/3 ratings and the stage projections, and inspected `composer.php`, `compose.css`, `reply_viewport.js` and the reply viewport tests before rating. This is a new dated rating by a different model from the historical rater, not an independent one. R1 three hashes verified 2026-11-03; P2; original baseline `63f0849691902e665506ad4f23f374353a32ae78` retained, implementation from `fd7e525df`.
- Stage originals copied from Step 3 (not new ratings): S1 0.08, S2 0.15, S3 0.12, S4 0.07; not additive.
- Pending ledger update: the ledger's cycle section (uncommitted, recovered from a conflicting stash on 2026-11-03 by keeping both sides) needs this entry checkpoint; it belongs in the closeout commit, not the planning commit.

## Enhancement requests

- **Request:** Word 97 / Midnight phone overflow and Sticker / Arena Post padding at 375px (from the original report). **Disposition and reason:** separate work; different cause (theme chrome, not the keyboard). **Approval status:** pending. **Follow-up:** separate cycle on request; see Step 1.

## Stage 1 - Order and phone-friendly flow

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `templates/partials/composer.php`: the full composer renders the tools row (tags, event) after `.compose-form-actions`; compact and reply composers are unchanged. The paned new-thread dialog, which renders the same full form, gets the new order too (Q3 default).
  - `public/assets/compose.css`: the reserved hidden image-picker footprint on `.compose-page` now applies only from 640px up; under 640px the dedicated page's body textarea is 7.5rem high (minimum 5.5rem) instead of the seven-row default.
  - `tests/LocalAppSmokeTest.php`: the rendered `/compose/thread` page has Title before body, body before the Post row, and the Post row before the tools; the board's compact form has no tools row. `tests/browser/composers.mjs`: the order assertion is now Title, body, Post, then tools.
- Verification:
  - Baseline captured before any change on `e55a06a09` (Chromium, 375 x 667 and 375 x 300, all 16 explicit themes; the site-specific `chouse`, `qdb` and `mitrapclub` themes fall back to the default on this site, so 13 distinct renderings): Post 291–330px below the top of the body, 46px of tools plus 8px of hidden picker between them; no horizontal overflow in any theme; document height 652–713px at 375 x 300.
  - After (Chromium and WebKit, same matrix): Post 142–151px below the top of the body in every theme (tools below Post, picker footprint gone, body 120px); still no horizontal overflow anywhere; document height 565–598px at 375 x 300. WebKit and Chromium agree within 1px.
  - `php tests/run.php LocalAppSmokeTest`: 157/160. The three failures (`testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`, `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`) are the same three recorded as pre-existing in the ui_tools_launcher and deployment_config summaries.
  - `node tests/browser/composers.mjs`: passed (New post with Title and tags, first line as title, menu wording, reply page, missing parent, both pages without JavaScript).
  - `node tests/browser/compose_disclosure.mjs`: passed.
  - `node tests/browser/compose_layout_stability.mjs`: **failed before and after** at the first `waitForLoadState('networkidle')` (line 86) in this environment; reproduced identically on the untouched planning commit in a scratch worktree, so it is not attributable to this stage. A 12-second request log of `/compose/thread` without the test's identity fixture shows no repeating requests; the cause is in the test's signed-identity path and is left for Stage 3, where that test is run again.
  - Desktop paned new-thread dialog (`/forte` at 1280 x 800, Chromium): Title, body, Post row, then tags; dialog 448 x 371px before and after; no overflow, no page errors; screenshot reviewed.
  - Existing theme defects: none found that block composer use (no horizontal overflow at 375px in any theme on this page). The Word 97 / Midnight / Sticker / Arena report stays under Enhancement requests.
- Notes:
  - Risk check from the plan: dropping the phone footprint cannot reintroduce the eligibility shift, because `image_upload.js` only un-hides `.image-compose-fields` once an image is attached (or an input notice exists), never on eligibility alone.
  - Playwright's WebKit binary was not installed in this environment; it was installed into the user cache (`~/.cache/ms-playwright/webkit-2248`) for the pinned `playwright-core` 1.58.1. No repository dependency changed.
  - Stage 1 alone does not keep Post visible with a 300px visible height (Post bottom is ~415px from the top of the page); that is Stage 2's docking.
- Estimate details: original scope as planned; no revision. Retrospective unchanged: the template and CSS work were as expected; the pre-existing test failure and the missing WebKit binary cost investigation time but are not this stage's scope.

*— Claude · 2026-11-03 UTC · Model: claude-fable-5-1*
