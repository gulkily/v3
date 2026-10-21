> **Feature plan:** [Step 1](./iphone_reply_submit_step1_solution_assessment.md) · [Step 2](./iphone_reply_submit_step2_feature_description.md) · [Step 3](./iphone_reply_submit_step3_development_plan.md) · [Step 4](./iphone_reply_submit_step4_implementation_summary.md)

Debug mode: enabled

## Problem

On iPhone, the keyboard can put the reply submit action out of reach below the editor. Approved Step 1 selects Option B: keep the existing action row at the visible viewport edge during editing and return it to ordinary flow afterward; actual-device reproduction is pending.

## User stories

- As an iPhone reader, I want Post reachable while writing so that I can reply without dismissing the keyboard or hunting for the button.
- As a replying user, I want my draft and a usable retry action after failure so that I can finish without retyping or posting twice.

## Core requirements

- In standard inline thread replies and the dedicated reply page, keep one primary submit action visible above the keyboard while editing, including long drafts, scrolling, and portrait/landscape transitions.
- Preserve comfortable access to the caret, selected text, validation errors, and recovery actions; the row must not cover content or introduce horizontal page overflow.
- Preserve draft text and selection through keyboard and layout changes; submitting retains existing identity, anonymous-reply, attachment, pending, and retry behavior without duplicate sends.
- Keep secondary actions reachable, restore ordinary layout when editing ends, and preserve desktop, assistive-technology, keyboard-navigation, and no-JavaScript use without repeating controls or adding explanatory copy.

## Delivery scope and completion boundary

**Work type:** Application change. Extend the shared reply UI for the two standard entry points, with focused regression coverage and verification records in this feature's implementation summary. Private messages, new-thread composition, and Forte redesign are excluded; shared consumers must retain their current behavior. No backend or data-model change is intended.

The slice begins at an ordinary thread Reply entry or dedicated reply page and ends with one accepted reply and the existing resulting thread navigation/update. Validation or a recoverable submission failure leaves the draft and retry usable. Release requires successful end-to-end checks and actual iPhone evidence; mockups and desktop emulation alone do not establish completion.

## Known environment and recovery

On an actual iPhone in Safari and Firefox, open each entry point, type with the software keyboard visible, scroll a long draft, and rotate. If validation or submission fails, the user can read the failure, correct the draft or retry through the visible action, and verify exactly one reply in the thread. Record device, OS/browser versions, orientation, and results, including keyboard reopening; another platform cannot satisfy this acceptance condition.

## Risks

- **Keyboard/browser viewport differences:** Post could be obscured or jump. Validate baseline and candidate feasibility on iPhone early; arrange device access before dependent implementation and stop dependent work if feasibility is unresolved.
- **Limited writing space:** The row could cover the caret or errors, especially in landscape or zoom. Check long drafts, scrolling, rotation, and zoom early; preserve clearance and readable recovery before proceeding.
- **Shared submission lifecycle:** Changing action placement could lose focus, alter anonymous submission, or send twice. Inspect existing shared behavior before staging; retain one action row and verify normal, pending, failure, and retry paths.

## Shared component inventory

- `reply_form.php`: canonical body, action row, and `/compose/reply` submission; extend rather than duplicate. Consumers: `thread.php` inline composer, `compose_reply.php` dedicated page, and `paned_board_compose_panel.php` in Forte (regression only).
- Existing compose action menu, image fields, normalization/feedback, identity/signing and submission handling, inline expansion, and shared compose styling: reuse their behavior and contracts; no new submission API or competing reply component.

## Simple user flow

1. Open Reply inline or on the dedicated page and focus the body.
2. Write, scroll, or rotate with Post reachable above the keyboard.
3. Post once; correct or retry a recoverable failure without losing the draft, then verify the reply in the thread.

## Success criteria

Both entry points pass the stated iPhone Safari/Firefox checks with short and long drafts, no obscured primary action or caret, and preserved draft/selection. A successful attempt creates exactly one reply; failure/retry remains usable. Desktop, no-JavaScript, Forte, and affected-theme checks pass, including Word97, Chicago, and Midnight, with limitations explicitly recorded.
