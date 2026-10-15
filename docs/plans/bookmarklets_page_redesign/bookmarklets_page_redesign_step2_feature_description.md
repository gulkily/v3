> **Feature plan:** [Step 1](./bookmarklets_page_redesign_step1_solution_assessment.md) · [Step 2](./bookmarklets_page_redesign_step2_feature_description.md) · [Step 3](./bookmarklets_page_redesign_step3_development_plan.md) · [Step 4](./bookmarklets_page_redesign_step4_implementation_summary.md)

Debug mode: disabled

## Problem
`/tools/bookmarklets/` shows draggable links styled like empty input fields, with a mismatched copy button, descriptions that visually attach to the wrong button, and two sections with identical labels. Visitors cannot tell what to drag, which description belongs to which bookmarklet, or which variant fits their device. Approved Option B restructures and restyles the page, scoped so other tool pages are unaffected.

## User stories
- As a visitor, I want a clear title, one-line purpose, and a "drag to your bookmarks bar" instruction so that I know what to do within seconds.
- As an iPhone user, I want the same-window and new-window variants clearly labeled by device so that I pick the one that works for me.
- As a visitor, I want each bookmarklet's button, copy action, and description presented as one unit so that I know what each does.
- As a visitor using any theme or a phone, I want readable type, comfortable spacing, and visible focus so that the page is easy to use.

## Core requirements
- Page hierarchy: title, short lead, drag instruction, then the two variant groups, each with a heading, quiet caveat note, and its bookmarklets.
- Each bookmarklet is a draggable chip-style link (content-sized, centered label, drag glyph, grab cursor) with a copy button of matching height; description sits close to its own button, with larger gaps between items.
- Row layout (button and copy left, description right) at 640 px and wider; stacked below that, including 375 px.
- Styling uses existing theme tokens only (no hard-coded colors), is scoped to this page, and keeps visible focus rings and readable `.meta` text in every theme.
- Bookmarklet generation, drag-to-bookmarks, copy behavior, and copy feedback are unchanged.

## Delivery scope and completion boundary
**Work type: application change** (template and CSS; no data, API, or schema changes). One day / at most eight stages. Release requires: open `/tools/bookmarklets/` in any theme at desktop and 375 px, drag a bookmarklet and copy one successfully, with other `/tools/` pages visually unchanged.

## Risks
- **Shared launcher styles:** `.tool-launcher-*` also styles `/tools/` and theme overrides (word97, qdb, nav icons). Earliest validation: before Step 3, list every selector and page using them; mitigate by scoping new rules to a bookmarklets page class and checking `/tools/`.
- **Theme coverage:** a dozen themes with distinct button and icon treatments may render the chip or glyph poorly. Validate with screenshots in all themes; mitigate by reusing existing token and nav-icon patterns.
- **Drag and copy regressions:** `tools_bookmarklets.js` finds links, copy buttons, and feedback by data attributes. Mitigate by keeping all hooks and the feedback element intact and testing drag and copy.
- **Contrast of `.meta` text:** notes can be low-contrast in chicago and vapor. Check both; fix via tokens, not literals.

## Shared component inventory
- `templates/pages/bookmarklets.php`: extend (page class, lead, instruction, grouping markup).
- `public/assets/tools.css`: extend with page-scoped rules; shared `.tool-launcher-*` base left intact for `/tools/`.
- `public/assets/theme-nav-icons.css`, `theme-word97.css`, `theme-qdb.css`: reuse existing icon and button treatments; add overrides only where a theme breaks.
- `public/assets/tools_bookmarklets.js` and `tools_nav.php`: unchanged.
- No new component or API needed.

## Simple user flow
1. Visitor opens `/tools/bookmarklets/` and sees the title, purpose, and drag instruction.
2. They pick the variant group matching their device.
3. They drag a chip to the bookmarks bar, or press copy beside it, and read its description beside it.
4. They use the bookmarklet on another page as before.

## Success criteria
- In all listed themes at desktop and 375 px, chips read as draggable, copy buttons match link height, and each description is closer to its own button than to the next.
- No horizontal scroll at 375 px; focus is visible on links and copy buttons.
- Drag and copy work as before; the existing bookmarklet smoke test assertions pass.
- `/tools/` and other `.tool-launcher-*` pages render unchanged.
