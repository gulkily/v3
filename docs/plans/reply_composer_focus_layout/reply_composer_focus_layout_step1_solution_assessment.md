> **Feature plan:** [Step 1](./reply_composer_focus_layout_step1_solution_assessment.md) · [Step 2](./reply_composer_focus_layout_step2_feature_description.md) · [Step 3](./reply_composer_focus_layout_step3_development_plan.md) · [Step 4](./reply_composer_focus_layout_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Can you please check the inline reply form for similar behavior, and it also for some reason features a floating post button that covers up the textarea... Maybe this should be a separate Railspike?

## Understood Intent

Make ordinary iPhone inline replies focus directly into one editor and keep Post reachable without covering the draft, caret or recovery feedback. This is a distinct follow-up to the merged [iPhone reply controls](../iphone_reply_submit/iphone_reply_submit_step4_implementation_summary.md). The separately authorized board single-editor fix is on `fix/board-compose-first-focus`; no branch reuse is requested for this cycle, and reply implementation has not started.

## Problem and evidence

The inline reply still switches from a readonly prompt to another textarea while scheduling container scrolling; the independently floating action row can overlap that editor. Source inspection confirms the handoff and fixed overlay. A supplementary Chromium probe using the real reply form and a simulated 420px visible viewport reproduced about 61px of editor/action-row overlap. This is not physical iPhone reproduction; earlier tests checked action visibility without asserting separation from the editor.

## Option A — One editor with actions in ordinary flow

Expand a single editable textarea and keep its action row in normal page layout, removing the floating overlay and competing focus/scroll handoff.

- Pros: Least moving layout; no overlay; straightforward desktop, keyboard and no-script behavior.
- Cons: Post can scroll out of view or behind the keyboard; relinquishes the previous continuous-reachability requirement.

## Option B — One editor with reserved editor and action space

Expand a single editable textarea into a phone composing area that allocates separate space to the editor, feedback and action row. Keep the action row reachable by resizing the writing area rather than placing controls over it.

- Pros: Preserves keyboard-open reachability and a clear caret; addresses both reported failures without duplicate controls.
- Cons: More viewport/layout coordination; landscape, zoom, browser chrome and long menus need careful checks, with ordinary-flow fallback when space is inadequate.

## Recommendation and viable slice

Prefer **B**, carrying the board's direct-focus interaction into inline replies while replacing the overlapping toolbar layout. Apply the non-overlap rule to the dedicated reply page too because it shares the action-row behavior. Begin at ordinary thread Reply or dedicated reply entry; end with exactly one accepted reply, or a retained draft with visible correction/retry after failure. Preserve identity, anonymous submission, attachments, selection, desktop, no-script use and Forte. Remove the inline reply's redundant collapsed disclosure marker along with the placeholder swap; retain the secondary-action menu.

Add explicit editor/action non-overlap checks alongside viewport-edge, first-focus, rotation, zoom, long-draft, failure/retry and duplicate-submit coverage. Existing desktop-engine checks supplement user iPhone Safari/Firefox acceptance. No device infrastructure is assumed or required before implementation; real-device results remain pending until the user tests the delivered change, consistent with their stated workflow. No schema change or deployment is proposed.

## Lessons from the board fix

One textarea must preserve the existing interaction, not merely remove the placeholder. The first board revision (`6a4ddbbc`) hid actions without restoring a truly compact prompt; the correction (`298b5a11`, now merged into `main`) restores visible editor expansion on focus and compact collapse when empty. For replies, keep the same editable node and selection throughout, retain drafts/attachments/errors in the expanded state, preserve secondary actions, and remove only the redundant disclosure arrow.

The initial collapsed layout must be correct before deferred JavaScript executes. Rendering full controls and hiding them during initialization caused Post to flash and move after paint. Verify both states with scripts held back, then compare geometry after initialization; also assert real editor height expansion and empty collapse. The board correction passed these checks in Chromium, Firefox and WebKit. Keep no-script use functional and avoid scripted focus transfer or competing scrolling.

Visible Post alone is insufficient: explicitly verify that the action row does not intersect the editor, caret or recovery feedback, including while the viewport changes. The previous reply checks passed action visibility while missing overlap. Browser evidence supports implementation; actual iPhone keyboard behavior remains user acceptance.

## Request disposition

- Request: Inspect similar reply focus behavior and floating Post overlap. Disposition: Separate Railspike because repairing continuous reachability without covering the editor requires a layout choice. Approval status: Step 1 assessment only; await fresh approvals before reply changes. Outcome: Recommendation B; the board fix remains independently reviewable and committed.

- Request: Record lessons from the board correction after merging it. Disposition: In-scope planning refinement; preserve collapse/expansion and first-paint stability explicitly alongside non-overlap. Approval status: Step 1 was approved; the user subsequently paused this reply cycle, and this lesson update does not resume it or authorize implementation. Outcome: Lessons above; Step 2 remains unwritten and planning files remain uncommitted under Railspike's planning-commit gate.
