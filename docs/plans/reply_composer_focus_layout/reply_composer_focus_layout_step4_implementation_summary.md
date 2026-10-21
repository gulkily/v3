> **Feature plan:** [Step 1](./reply_composer_focus_layout_step1_solution_assessment.md) · [Step 2](./reply_composer_focus_layout_step2_feature_description.md) · [Step 3](./reply_composer_focus_layout_step3_development_plan.md) · [Step 4](./reply_composer_focus_layout_step4_implementation_summary.md)

Debug mode: enabled

## Execution state

- Branch: `feat/reply-composer-focus-layout`; approved plans/debug log organized and committed as `e690c0d2` before this summary or application changes.
- Step 3 approved. No effort study selected. Actual iPhone Safari/Firefox acceptance remains pending user testing, with no upfront device gate. No merge, push or deployment authorized.
- Request state: Preserve the board correction's genuine collapse/expansion and stable first paint; implement approved Option B for standard reply forms. No additional or deferred implementation requests.

## Stage 1 - One editor and preserved inline interaction

- Changes: Standard inline replies now render the canonical editable body directly in a server-collapsed wrapper, without a readonly prompt or disclosure arrow. Board/reply composers share the existing state lifecycle and collapsed CSS. Reply links use direct native focus once; no deferred focus handoff or scripted scrolling. Signing recognizes the new inline wrapper; original form controls and APIs remain intact. Updated consumers/tests that targeted the removed prompt.
- Verification passed: `node tests/browser/reply_compose_focus.mjs` covers 390px/1280px across Light/Word97/Chicago/Midnight, scripts-held first paint and initialized geometry, same editor/one focus, actual height expansion/empty collapse, drafts/selection, error/retry and one real reply, no-script controls. `inline_reply_link.mjs` covers native reveal, modified/middle-click and no-script fallback; `board_compose_focus.mjs` passes unchanged board behavior. `php tests/run.php LocalAppSmokeTest::testApplicationRendersCoreRoutes BrowserSigningNormalizationTest LazyComposeSigningTest` passes 86/86 after replacing obsolete prompt/scroll source assertions. Changed PHP/JS syntax and diff checks pass.
- Test corrections: Thread pages make background requests, so first-paint coverage waits for explicit script initialization rather than network idle. Reply success waits for the resulting post anchor before counting records, rather than matching the thread URL that was already open.
- Notes: Stage 2 has not started. Physical iPhone behavior remains pending user acceptance; existing phone toolbar behavior is replaced in Stage 2.
