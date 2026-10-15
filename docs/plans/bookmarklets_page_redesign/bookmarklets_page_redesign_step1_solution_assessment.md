> **Feature plan:** [Step 1](./bookmarklets_page_redesign_step1_solution_assessment.md) · [Step 2](./bookmarklets_page_redesign_step2_feature_description.md) · [Step 3](./bookmarklets_page_redesign_step3_development_plan.md) · [Step 4](./bookmarklets_page_redesign_step4_implementation_summary.md)

Debug mode: disabled

## Original Query
Please review bookmarklets_design.txt and also imagine you're a UX/UI designer at Apple, Airbnb, or Stripe, and you're tasked with redesigning this page according to principles of good design like high-quality typography, white space, hierarchy, etc. Please write Step 1 of railspike.md to improve/redesign this page.

## Understood Intent
Redesign `/tools/bookmarklets/` so it reads as a calm, well-ordered page: a clear title and one-line purpose, an obvious "drag this" affordance, and each bookmarklet visibly grouped with its own description. The existing fix brief (`bookmarklets_design.txt`) is the baseline problem list: links look like empty inputs, the copy button is mismatched, descriptions sit flush under the wrong button, and same-window vs. new-window is unclear. Bookmarklet generation, drag-to-bookmarks, and copy behavior are unchanged.

## Problem Statement
The bookmarklets page presents draggable links as input-like fields with weak spacing and grouping, so users cannot tell what to do, which button a description belongs to, or which variant suits their device.

## Option A: Styles-only fix from the brief
Apply the brief's CSS changes to the shared `.tool-launcher-*` and `.bookmarklet-*` styles: content-sized chip with a drag glyph, matched copy-button height, tighter button-to-description gap and larger gap between items. Page markup stays as is.

- **Pros:** Smallest change; every theme inherits it; no template churn.
- **Cons:** Fixes symptoms, not hierarchy: the long intro paragraph, two same-looking sections, and repeated Clip/Rip/Tweet labels remain; shared styles risk regressing other `/tools/` pages.

## Option B: Scoped page redesign (structure + type + spacing)
Restructure the page with a defined hierarchy: title, short lead sentence, and a single "Drag to your bookmarks bar" instruction up front. Present the two variants as distinct, labeled groups (device guidance in the heading, caveat as a quiet note), with each bookmarklet as one unit: a chip-style draggable button and copy action on the left, its description on the right (stacked on narrow screens). Use a deliberate type scale, generous whitespace, and theme tokens only, scoped to this page so other tool pages are untouched.

- **Pros:** Addresses hierarchy, grouping, and the same-window/new-window confusion together; scoped styles protect other `/tools/` pages; includes all of Option A's fixes.
- **Cons:** Touches the template as well as CSS; needs visual checks across all themes at desktop and 375px widths.

## Option C: Device-first guided layout
Detect or ask the visitor's device and show only the relevant variant (same-window for iPhone, new-window otherwise), with the other behind a "Show other variant" disclosure.

- **Pros:** Least visual noise; removes the variant decision for most users.
- **Cons:** Needs device detection or a new control and extra states to test; hides options users may want (e.g., copying for another device); larger than one slice.

## Recommendation
Choose **Option B**, incorporating the brief's fixes from Option A (chip affordance, matched copy button, focus rings, spacing, `.meta` contrast). It is a single releasable slice: a visitor lands on the page, immediately understands the drag instruction, picks the right variant, and drags or copies a bookmarklet exactly as before. Defer Option C's device-aware filtering as a possible follow-up once the clearer layout is in place.
