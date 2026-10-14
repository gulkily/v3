> **Feature plan:** [Step 1](./bookmarklet_one_line_copy_step1_solution_assessment.md) · [Step 2](./bookmarklet_one_line_copy_step2_feature_description.md) · [Step 3](./bookmarklet_one_line_copy_step3_development_plan.md) · [Step 4](./bookmarklet_one_line_copy_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Bookmarklets currently offer drag-to-save links without a convenient way to copy their source onto one line for qutebrowser. Users need a compact copy action without selectable source fields.

## User Stories

- As a qutebrowser user, I want to copy a bookmarklet in one action so that I can add or bind it without manually flattening its source.
- As a keyboard user, I want to identify and activate each copy button and receive feedback so that I know whether copying succeeded.

## Core Requirements

- Add a small, icon-only copy button beside every existing bookmarklet, with a descriptive accessible name, tooltip, and visible keyboard focus; add no selectable source field.
- Copy the complete executable bookmarklet, including its `javascript:` prefix, on one physical line without surrounding quotes or a browser-specific command wrapper. Preserve the current site's destination and the selected variant's behavior.
- Keep copying consistent with the draggable link, including text escaping, intentional content newlines, tweet attribution, and same-window/new-window behavior. Copying must not execute the bookmarklet or navigate away.
- Show brief, accessible success feedback only after successful copying; report failure accurately and allow retry without adding persistent clutter.
- Preserve existing page grouping and drag-to-save use across themes and narrow screens; do not introduce format selectors, new bookmarklet variants, or configuration editors.

## Delivery Scope and Completion Boundary

**Work type:** application change, confined to the existing Bookmarklets experience and relevant verification. Normal entry is Tools → Bookmarklets. Completion means users can copy and use an existing variant in qutebrowser, with clear failure/retry behavior. Release requires coverage of all seven displayed entries and a recorded qutebrowser copy-to-binding execution check; missing browser verification must be reported as incomplete. Step 1's eight combinations described generator coverage, not the displayed inventory.

## Risks

- **Source transformation:** flattening can corrupt strings or behavior. Earliest validation: compare representative ordinary/tweet output before implementation; mitigation: require behavioral parity, including escaped content newlines, for every displayed variant.
- **Clipboard availability:** browser restrictions may prevent copying. Earliest validation: exercise allowed, denied, and unavailable clipboard paths; mitigation: choose a compatible fallback where feasible, with truthful failure feedback and retry in every case.
- **qutebrowser interpretation:** URL decoding or command quoting may alter copied text. Early documentation review confirms support for bookmarklet URLs via the URL option of JavaScript evaluation and command bindings ([official reference](https://qutebrowser.org/doc/help/commands.html#jseval)); mitigation: plan actual browser execution before release rather than treating documentation as compatibility proof.

## Shared Component Inventory

- Extend the canonical [Bookmarklets template](../../../templates/pages/bookmarklets.php), [page controller](../../../src/ForumRewrite/Http/ToolsPageController.php), [source generator](../../../public/assets/tools_bookmarklets.js), and [Tools styling](../../../public/assets/tools.css). Live and generated static pages share this experience.
- Keep the Tools index and shared navigation as existing entry points; neither renders bookmarklet source. No bookmarklet API or separate export surface is needed.
- Existing invitation and account-key copy controls handle other data; reuse suitable visual/feedback conventions without importing their unrelated workflows.

## Simple User Flow

1. Open Tools → Bookmarklets and choose a variant.
2. Activate its copy icon and receive success or failure feedback; retry if needed.
3. Paste the copied value into qutebrowser's bookmarklet/binding workflow and run it on a source page.

## Success Criteria

- All seven entries copy complete source containing no physical line breaks and retain their existing behavior.
- Pointer and keyboard copying succeed without executing the link; failures never produce success feedback.
- A recorded qutebrowser binding check reaches Compose Thread with expected content and window behavior.
- No selectable source fields appear; icons, focus, and feedback remain usable across supported themes and narrow layouts.
