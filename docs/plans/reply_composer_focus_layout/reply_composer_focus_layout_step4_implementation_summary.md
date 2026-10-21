> **Feature plan:** [Step 1](./reply_composer_focus_layout_step1_solution_assessment.md) · [Step 2](./reply_composer_focus_layout_step2_feature_description.md) · [Step 3](./reply_composer_focus_layout_step3_development_plan.md) · [Step 4](./reply_composer_focus_layout_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/reply_composer_focus_layout · [Host ledger](../effort_estimation_trial.md)

## Execution state

- Branch: `feat/reply-composer-focus-layout`; approved plans/debug log organized and committed as `e690c0d2` before this summary or application changes.
- Step 3 approved. At Step 4 entry no effort study was selected; subsequent P2 standing enrollment is recorded below. Actual iPhone Safari/Firefox acceptance remains pending user testing, with no upfront device gate. No merge, push or deployment authorized.
- Request state: Preserve the board correction's genuine collapse/expansion and stable first paint; implement approved Option B for standard reply forms. No additional or deferred implementation requests.

## Stage 1 - One editor and preserved inline interaction

- Changes: Standard inline replies now render the canonical editable body directly in a server-collapsed wrapper, without a readonly prompt or disclosure arrow. Board/reply composers share the existing state lifecycle and collapsed CSS. Reply links use direct native focus once; no deferred focus handoff or scripted scrolling. Signing recognizes the new inline wrapper; original form controls and APIs remain intact. Updated consumers/tests that targeted the removed prompt.
- Verification passed: `node tests/browser/reply_compose_focus.mjs` covers 390px/1280px across Light/Word97/Chicago/Midnight, scripts-held first paint and initialized geometry, same editor/one focus, actual height expansion/empty collapse, drafts/selection, error/retry and one real reply, no-script controls. `inline_reply_link.mjs` covers native reveal, modified/middle-click and no-script fallback; `board_compose_focus.mjs` passes unchanged board behavior. `php tests/run.php LocalAppSmokeTest::testApplicationRendersCoreRoutes BrowserSigningNormalizationTest LazyComposeSigningTest` passes 86/86 after replacing obsolete prompt/scroll source assertions. Changed PHP/JS syntax and diff checks pass.
- Test corrections: Thread pages make background requests, so first-paint coverage waits for explicit script initialization rather than network idle. Reply success waits for the resulting post anchor before counting records, rather than matching the thread URL that was already open.
- Notes: Stage 2 has not started. Physical iPhone behavior remains pending user acceptance; existing phone toolbar behavior is replaced in Stage 2.

## Stage 2 - Separate phone writing, feedback and actions

- Changes: Standard inline and dedicated replies now share a bounded phone composing panel. The original form, textarea, feedback and action row stay together; the editor flexes into the remaining space and Post stays in normal layout within the panel. Open menus reserve their footprint instead of covering the editor. A noninteractive spacer preserves page flow during editing. Focus exit, zoom, tiny viewports or oversized feedback/attachments restore ordinary flow without moving/replacing controls or clearing the draft.
- Verification passed: Chromium `reply_viewport.mjs` reproduces the previous overlap at multiple page offsets with a simulated 420px viewport, asserts editor/action/error/menu separation, preserves long draft/selection through viewport changes/scroll/rotation, tests readable menus/native primary and anonymous submitters, oversized-feedback/tiny-viewport/zoom fallback, focus restoration, desktop and Forte exclusion across Light/Word97/Chicago/Midnight. Firefox and WebKit pass the same viewport matrix, including fallback checks, on final runs. The first WebKit run timed out before finding the synthetic form; rerunning the same check passed all assertions.
- Verification passed: `reply_viewport_flow.mjs` covers both isolated real reply routes, recoverable failure, retained draft, retry, duplicate pending activation and exactly one persisted reply. `compose_layout_stability.mjs` passes 24 route/theme/viewport/permission cases and both Outbox saves. The Stage 1 reply focus/paint/collapse matrix also passes with the new panel. JS/PHP syntax and diff checks pass. Stage 1 commit: `30846b00`.
- Notes: Original reply tests asserted only action visibility; the matrix now explicitly rejects intersections and verifies fallback. Physical iPhone acceptance remains pending. Concurrent changes to project workflow/study files are outside this feature's code changes and will not be included in its stage commit.

## Effort study enrollment and Step 4 discovery snapshot

- Recorded UTC: 2026-10-21T18:01:30.581350+00:00.
- Study/cycle: `railspike-effort` / `v3/reply_composer_focus_layout`; parent: merged `iphone_reply_submit` cycle (not previously assigned a study ID). Protocol P2 standing enrollment appeared in the project's AGENTS/ledger during Stage 2; this existing cycle joins at this checkpoint. Intake and Steps 1–3/Step 4 entry projections are missing, not reconstructed.
- Scope v1: approved Step 2/3 contract, starting revision `bc42277b`; single-editor inline entry, non-overlapping phone composing space in both reply routes, real submission/recovery and shared-consumer checks, with actual iPhone user acceptance pending. No scope expansion or split.
- Reference R1: calibration report SHA-256 `d3a30b6400b83bb25283b4234b41ba07515df9a696ea44de59c10186103771b0` and diff review `068b3519326e08a939df40b5abe0106ddcbab80bd796616c76b5084a21462b84` verified in `/home/wsl/railspike-dev/docs/research/effort_estimation/`. Frozen feedback SHA-256 `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc` restored and verified from Git revision `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` rather than silently using updated feedback.
- Rater/exposure: Codex, single rater; approved plans, original reply implementation/feedback and current Stage 1/2 work were visible. This is an exposed estimate collected after implementation began, not an independent or prospective intake rating.
- Total projected scope score: **0.65** from the cycle's starting point, including planning, implementation, required verification/documentation and ordinary corrections. Similar to R1 composer decluttering (0.60), with additional viewport/focus recovery checks; smaller than the package/recovery startup references. This is not remaining work, elapsed effort or user acceptance.
- Approval context: Steps 1–3 approved; study collection does not widen implementation scope. Ledger index update will be recorded at closeout; unrelated concurrent ledger changes must remain outside stage commits. Maintainer comfortable-fit judgment and accepted study outcome remain pending.
