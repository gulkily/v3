> **Feature plan:** [Step 1](./iphone_reply_submit_step1_solution_assessment.md) · [Step 2](./iphone_reply_submit_step2_feature_description.md) · [Step 3](./iphone_reply_submit_step3_development_plan.md) · [Step 4](./iphone_reply_submit_step4_implementation_summary.md)

Debug mode: enabled

## Execution state

- Branch: `feat/iphone-reply-submit`.
- Step 3 explicitly approved; approved plans and existing debug log organized and committed before this summary was created. Mockup review assets moved alongside the plans and remain uncommitted, excluded from the planning commit.
- Application behavior has not changed. No deployment or merge is authorized.
- No effort study is selected for this cycle.

## Stage 1 - Establish iPhone feasibility (pending)

- Changes: Restored the approved scope and device prerequisites; checked available tool metadata for browser/device access. No actual iPhone connection is exposed. Asked the user to arrange Safari and Firefox testing against an isolated instance or identify an existing device-testing connection.
- Verification: Planning commit file scope and `git diff --cached --check` passed. Actual iPhone baseline and candidate feasibility are pending, including both entry points, short/long drafts, keyboard opening/reopening, scrolling, selection, rotation, and zoom. Device, OS/browser versions, isolated-instance URL, and results remain unrecorded because no device session is available.
- Notes: Stage 1 is incomplete and has no completed-stage commit. Stage 2 is blocked by the approved Stage 1 dependency; emulation cannot satisfy this gate. No application tests were run because there are no application changes.

## Remaining stages

- Stage 2: Pending Stage 1 feasibility pass and stage commit.
- Stage 3: Pending implementation and actual-device end-to-end acceptance, including recovery and exactly one created reply.

## Next action

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
