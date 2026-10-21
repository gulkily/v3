> **Feature plan:** [Step 1](./iphone_reply_submit_step1_solution_assessment.md) · [Step 2](./iphone_reply_submit_step2_feature_description.md) · [Step 3](./iphone_reply_submit_step3_development_plan.md) · [Step 4](./iphone_reply_submit_step4_implementation_summary.md)

Debug mode: enabled

## Current outcome

Implementation is ready for user testing on `feat/iphone-reply-submit`. Actual iPhone Safari/Firefox keyboard, caret, selection, rotation, zoom, and recovery acceptance remain **pending**. The user explicitly authorized finishing without device infrastructure. Automated checks supplement that acceptance; no production deployment or merge occurred.

## Initial execution state

- Branch: `feat/iphone-reply-submit`.
- Step 3 explicitly approved; approved plans and existing debug log organized and committed before this summary was created. Mockup review assets moved alongside the plans and remain uncommitted, excluded from the planning commit.
- Application behavior has not changed. No deployment or merge is authorized.
- No effort study is selected for this cycle.

## Initial Stage 1 assessment (superseded by user override)

- Changes: Restored the approved scope and device prerequisites; checked available tool metadata for browser/device access. No actual iPhone connection is exposed. Asked the user to arrange Safari and Firefox testing against an isolated instance or identify an existing device-testing connection.
- Verification: Planning commit file scope and `git diff --cached --check` passed. Actual iPhone baseline and candidate feasibility are pending, including both entry points, short/long drafts, keyboard opening/reopening, scrolling, selection, rotation, and zoom. Device, OS/browser versions, isolated-instance URL, and results remain unrecorded because no device session is available.
- Notes: Stage 1 is incomplete and has no completed-stage commit. Stage 2 is blocked by the approved Stage 1 dependency; emulation cannot satisfy this gate. No application tests were run because there are no application changes.

## Initial remaining stages (superseded)

- Stage 2: Pending Stage 1 feasibility pass and stage commit.
- Stage 3: Pending implementation and actual-device end-to-end acceptance, including recovery and exactly one created reply.

## Initial next action (superseded)

Establish actual iPhone access and an isolated reachable instance. Record device and browser versions, reproduce both reply flows, and validate the viewport-edge candidate before starting Stage 2. Preserve pending status until evidence is available; do not treat the planning commit as a Stage 1 pass.

## User-directed execution change

- Request: Finish implementation for the user to test; no iPhone testing infrastructure is available.
- Disposition: Explicit user override of the device-first dependency. Proceed with automated browser feasibility and implementation, retaining actual iPhone checks as pending user acceptance. Product scope is unchanged.
- Approval status: Authorized by the user's instruction following Step 3 approval.
- Outcome: Stage 1 completed as environment assessment under this override; no actual-device feasibility pass is claimed. Stages 2 and 3 may proceed with browser evidence. Device acceptance remains pending.

## Stage 1 - Environment assessment completed under user override

- Changes: Confirmed no actual-device connection is exposed. Retained mockups as review assets; no application probe required.
- Verification: Local plan links and `git diff --check` passed. Actual iPhone feasibility pending, explicitly waived as an implementation prerequisite by the user.
- Notes: Continue Stage 2 on this branch. Device checks will be handed to the user after implementation; production deployment remains unauthorized.

## Stage 2 - Shared phone reply behavior

- Changes: Added opt-in viewport behavior to the canonical reply form for standard inline thread replies and the dedicated reply page. The original action row stays in its form; no controls are cloned or reparented. On a touch phone while writing, its fixed position follows VisualViewport resize/scroll, with form clearance, bounded editor height, and a scrollable secondary-action menu. Focus exit, desktop, unsupported viewports, and zoom retain ordinary flow. Forte does not opt in. Pointer interaction holds placement through the click; automatic initialization focus alone does not activate it.
- Verification passed: `node tests/browser/reply_viewport.mjs` (four themes, shrinking/reopening viewport, offset/scroll, rotation, long draft/selection preservation, menu bounds, original primary/anonymous submitters, zoom fallback, Forte exclusion and desktop non-activation); Firefox run of the same test; `node tests/browser/reply_actions_layout.mjs` (20 cases); `node tests/browser/inline_reply_link.mjs` (phone/desktop and no-JavaScript fallback); `node tests/browser/compose_disclosure.mjs`; `node tests/browser/compose_layout_stability.mjs` (24 real-route/theme/viewport/permission cases plus Outbox saves); `php tests/run.php BrowserSigningNormalizationTest LazyComposeSigningTest OfflineOutboxComposeTest ImageContentTest` (91/91). Changed PHP files and JS syntax checks and `git diff --check` passed.
- Verification passed: Isolated `node tests/browser/image_content_browser.mjs` exercises signed thread/reply publication, lost-response retry without duplication, anonymous attachment draft preservation, image delivery, Forte rendering, backup/restore and CLI rebuild. Corrected its stale Forte action inventory assertion to include the already-present Outbox action; product Forte controls were not changed.
- Corrections: Focused theme checks caught and corrected Word97 spacing overriding the fixed row's margin. First-paint regression caught and corrected activation from automatic focus.
- Notes: Mock viewport tests and desktop browser engines do not establish physical iPhone keyboard behavior. Actual Safari/Firefox iPhone acceptance remains pending user testing under the recorded override. No UI copy or navigation destination changed; copy cleanup and icon remapping are inapplicable. No backend/schema changes or production deployment.

## Stage 3 - Regression verification and user-test handoff

- Changes: Added isolated real-route reply acceptance coverage for both standard entry points. Visual review corrected an overly narrow expanded action menu and added a readable-width regression assertion. Retained docking when Safari leaves focus on the document while the action menu is open; moving focus outside the form restores ordinary flow. Updated Feature 16 feedback status to implemented for testing, with actual-device acceptance pending.
- Verification passed: `node tests/browser/reply_viewport_flow.mjs`, `REPLY_BROWSER=webkit node tests/browser/reply_viewport_flow.mjs`, and `REPLY_BROWSER=firefox node tests/browser/reply_viewport_flow.mjs`: create a real signed thread, enter each reply UI, force a recoverable network failure, preserve the draft, retry, repeat submit during the pending request, and verify exactly one persisted reply after reload. These use temporary isolated repositories and a loopback server; no production records are touched. WebKit navigation-cancelled background requests are collected separately, matching the existing browser harness convention; unexpected page errors fail the check.
- Verification passed: Final viewport regression covers Chromium, WebKit and Firefox with simulated keyboard dimensions, viewport offsets, scroll, rotation, long draft/selection, expanded menu bounds and readability, Safari-style unfocused menu, zoom fallback, restored flow, desktop, and Forte exclusion. Frame-based waits replace timing assumptions. Browser engines run on desktop Linux; this is not iPhone device evidence.
- Visual review passed: Inspected rendered Light/Chicago editing and Word97/Midnight expanded-menu screenshots; after the menu-width correction, inspected Word97/Midnight again. Screenshots generated by `REPLY_SCREENSHOTS=/tmp/iphone-reply-review node tests/browser/reply_viewport.mjs` remain local review artifacts. Initial/loaded action stability is covered by the 24-case real-route check; empty no-script and desktop inline entry are covered by the existing inline test. Validation/normalization and signing/pending recovery are covered by the 91 focused PHP tests. No full-suite pass is claimed.
- Commit references: Planning `3e124a39`; Stage 1 `3e5023f9`; Stage 2 `68220bb3`. This stage's fixes, coverage, feedback status, and summary are committed together; final history records its hash.
- Pending: Actual iPhone Safari and Firefox with software keyboard, browser chrome, real selection/caret and pinch zoom; user acceptance. Feature 16 remains unresolved at the device-acceptance boundary.
- Verification passed: Final JS syntax checks, feature plan local-link validation and `git diff --check`.
- Inapplicable: Database migration/read-model changes, copy cleanup, navigation icon changes, deployment and merge; none are part of this implementation.

## User test

1. On the test instance using this branch, open a thread's ordinary Reply entry and type with the iPhone keyboard open. Confirm Post stays visible; try a long draft, scrolling and rotation.
2. Repeat on the dedicated reply page, including closing/reopening the keyboard and opening the action menu.
3. Submit once and confirm one reply. If a connection failure occurs, confirm the draft survives and retry works without duplication.
4. Report the iPhone model, iOS version, Safari/Firefox version and any obscured action, caret, error, or lost selection. Actual-device acceptance is intentionally left to the user under the explicit override.

## Board composer follow-up — 2026-10-21

- Request: Use one textarea that expands in place on the main feed; remove the collapsed arrow above it. Disposition: Small direct bug fix on `fix/board-compose-first-focus`, separately authorized by the user; the earlier cycle's completed records remain unchanged. No new Railspike cycle was needed for this bounded replacement.
- Changes: Removed the board's readonly prompt textarea and details/summary wrapper. The canonical thread form's editable body is visible from the start, with the original board prompt. JavaScript reveals the existing actions on focus, keeps drafts/attachments/warnings expanded, and collapses empty actions on focus exit. It never transfers focus or scrolls the board composer. The disclosure arrow is absent; no-script forms show their actions.
- Passed: `php tests/run.php LocalAppSmokeTest::testApplicationRendersCoreRoutes BrowserSigningNormalizationTest LazyComposeSigningTest` (86/86); `node tests/browser/board_compose_focus.mjs` and Firefox/WebKit variants cover 390px/1280px, Light/Word97/Chicago/Midnight, same editor identity and one direct first focus, no scripted scrolling, preserved draft/selection, empty collapse, failure/retry and one real thread, and no-JavaScript controls. WebKit needed a longer navigation timeout after an initial page-load timeout; all assertions passed on rerun.
- Passed: `IMAGE_PICKER_ONLY=1 node tests/browser/image_content_browser.mjs` covers native chooser, cancellation/empty collapse, picker-focus race and image-only/text-plus-image publishing from the board and dedicated page. Existing `inline_reply_link.mjs` passes unchanged reply behavior. PHP/JS syntax and diff whitespace checks passed. Inspected phone Word97 collapsed and Midnight expanded screenshots with no disclosure arrow or overflow.
- Pending: Physical iPhone verification of first-tap keyboard behavior remains user acceptance; desktop engines do not reproduce the software keyboard.
- Separate request: Inspect inline reply for the same focus behavior and a floating Post row obscuring the textarea. Disposition: New Railspike Step 1 assessment; no reply implementation changes in this board patch. Approval status: Assessment requested, implementation pending new approvals. Outcome: See the new `reply_composer_focus_layout` assessment in `docs/plans/`.
