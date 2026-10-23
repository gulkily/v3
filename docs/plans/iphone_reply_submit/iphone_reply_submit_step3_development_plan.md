> **Feature plan:** [Step 1](./iphone_reply_submit_step1_solution_assessment.md) · [Step 2](./iphone_reply_submit_step2_feature_description.md) · [Step 3](./iphone_reply_submit_step3_development_plan.md) · [Step 4](./iphone_reply_submit_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

From ordinary thread Reply or the dedicated reply page, an iPhone user can write and submit exactly one reply with Post visible above the open keyboard. Draft/selection survive layout changes; validation and recoverable failure expose correction/retry without retyping. Preserve desktop layout/behavior, secondary actions, no-JavaScript use, and Forte. Actual iPhone Safari/Firefox evidence is required; emulation is supplementary. Test submission against an isolated instance; production deployment is not authorized. Release readiness remains pending until device checks and applicable regressions pass.

## Key Risks

- **High risk:** Keyboard, rotation, or browser chrome can hide Post or the caret. Stage 1 must establish actual-device feasibility before dependent changes; unresolved results block Stage 2.
- **High risk:** Relocating actions can change focus or submission and cause duplicate replies. Preserve the canonical form and submitter behavior; exercise failure/retry and pending state before acceptance.
- Shared styling can affect desktop/Forte. Limit activation to the standard phone reply editing context, restore normal flow on exit, and check non-activation explicitly. No backend/schema change is planned.

## Stage 1 — Establish iPhone feasibility

- Goal: Resolve device access and establish that Option B can preserve usable writing space.
- Dependencies: Approved Step 3 and Step 4 planning/branch gate; actual iPhone access and an isolated reachable instance.
- Expected changes: Record baseline and bounded feasibility evidence in the implementation summary; any disposable probe stays isolated from application behavior.
- Verification approach: On Safari and Firefox, replay both entry points with short/long drafts, keyboard open/close, scrolling, selection, rotation, and zoom; record device/OS/browser versions and candidate viewport-edge behavior.
- Risks or open questions: Device access is unconfirmed. If unavailable, stop dependent implementation with evidence pending; if the approach fails, return to the affected planning step rather than silently switch options.
- Canonical components/API contracts touched: Standard thread and dedicated reply flows, shared form/action placement; no submission contract change.

## Stage 2 — Deliver the shared phone reply behavior

- Goal: Make the two standard reply flows usable with the keyboard open, end to end.
- Dependencies: Stage 1 feasibility passed and committed.
- Expected changes: Extend shared reply layout and page wiring with one action row at the visible viewport edge during phone editing; reserve writing/error space and restore ordinary flow on exit. Add focused behavior coverage; retain existing menus, identity, anonymous submission, and attachments.
- Verification approach: Verify activation/restoration, viewport changes, scrolling, preserved selection, menu focus, and unchanged desktop/Forte layout. On the isolated instance exercise successful submission, pending/repeated activation, validation failure, and recoverable retry; count created replies. Run relevant existing inline reply, action-layout, disclosure, layout-stability, signing, and image-transport checks plus changed-file syntax checks.
- Risks or open questions: Action movement may blur the editor or change the submitter; inspect the existing lifecycle before edits and block acceptance on focus loss, covered errors, or duplicate sends. Unsupported viewport behavior must retain usable ordinary form flow.
- Canonical components/API contracts touched: `reply_form.php`, standard thread/compose page wiring, shared compose styling, asset loading and compose lifecycle as needed; reuse `/compose/reply`, existing signing, menus, and attachment contracts.

## Stage 3 — Confirm device outcome and regressions

- Goal: Prove the reported iPhone flow and required recovery work in the delivered application.
- Dependencies: Stage 2 verification passed and committed; actual iPhone access continues.
- Expected changes: Record final evidence, limitations, and resolution status in the implementation summary and feedback summary. Correct in-scope failures and rerun affected checks before completion.
- Verification approach: Replay Stage 1 on actual Safari/Firefox, including keyboard reopening, failure/correction/retry, and one resulting reply. Check phone/desktop initial, loaded, empty, pending, failure, success, and expanded-menu states, long content/overflow, zoom and keyboard navigation; cover affected themes including Word97, Chicago, Midnight. Verify desktop without keyboard, no-JavaScript flow, and Forte retain current behavior.
- Risks or open questions: Emulated passes cannot substitute for device acceptance. Record any missing evidence as pending; do not close Feature 16 or call the UI complete while required checks are unresolved.
- Canonical components/API contracts touched: Delivered standard reply flows and shared consumers; feature summary and source feedback status.

## Execution and request record

- After Approved Step 3, create a feature branch, organize this cycle's plans/debug log and mockups without changing approved scope, repair links/index, and make the required planning commit. Keep mockup review assets outside that commit unless allowed by the planning-commit gate. Create Step 4 afterward; each stage must pass applicable checks, update the summary, and receive its own commit before the next begins. No merge or deployment is authorized.
- **Request:** Will desktop be affected? **Disposition:** In-scope clarification, incorporated before Step 2 approval. **Approval status:** Step 2 approved. **Outcome:** Desktop appearance/behavior stays unchanged; explicit non-activation and shared-consumer regressions above verify this boundary.
