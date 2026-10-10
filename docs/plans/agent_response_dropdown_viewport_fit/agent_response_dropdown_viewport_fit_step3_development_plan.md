> **Feature plan:** [Step 1](./agent_response_dropdown_viewport_fit_step1_solution_assessment.md) · [Step 2](./agent_response_dropdown_viewport_fit_step2_feature_description.md) · [Step 3](./agent_response_dropdown_viewport_fit_step3_development_plan.md) · [Step 4](./agent_response_dropdown_viewport_fit_step4_implementation_summary.md)

# Agent Response Dropdown Viewport Fit – Development Plan

## Completion Contract
- Normal entry: a reader clicks "request agent reply" on a post or thread root at any scroll position.
- End-to-end outcome: the full menu is visible and attached to its button on open, scroll, and resize; choosing a mode submits the request as today.
- Required recovery: Escape, outside click, close button, or the button leaving the viewport closes the menu and returns the page to its prior state; no data is affected.
- Deployment/external verification: none beyond serving the updated static assets; no server, API, or data changes.
- Release condition: manual check on desktop and a short/narrow viewport in each shipped theme, plus no regression in existing PHP tests.

## Key Risks
- **High risk:** usability — menu clipped or unreachable on very short viewports. Validate by resizing to small heights; mitigate with a minimum height plus internal scrolling.
- Scroll/resize handlers leak or cause jank. Validate by opening and closing repeatedly while scrolling; mitigate by attaching listeners only while open and removing them on close.
- Theme chrome (fixed headers) overlaps the menu. Validate in each theme; mitigate with one shared viewport margin, adjusted only if needed.

## Stage 1
- Goal: Choose the side with more room and size the menu to the space available there, so it is never clipped on open.
- Dependencies: none.
- Expected changes:
  - Update the existing placement function to compare space above and below the button, preferring below.
  - Set the menu's max height from the chosen side's available space (with a minimum), keeping internal scrolling.
  - Adjust the menu's static max-height rule so the computed value takes precedence.
- Verification approach: manual open from posts near the top, middle, and bottom of the viewport; short window height check.
- Risks or open questions:
  - Impact: menu shorter than its content hides choices behind scroll.
  - Early warning / validation: test at small window heights.
  - Mitigation: minimum height and visible scroll area.
- Canonical components/API contracts touched: agent response mode menu placement (`post_analysis.js`) and styling (`content-interactions.css`).

## Stage 2
- Goal: Keep the open menu anchored to its button on scroll and resize, and close it when the button leaves the viewport.
- Dependencies: Stage 1.
- Expected changes:
  - Register scroll (capture) and resize listeners when the menu opens; re-run placement on each.
  - Close the menu when the trigger's bounds are fully outside the viewport.
  - Remove the listeners in the existing close path.
- Verification approach: manual scroll and resize with the menu open; open/close repeatedly and confirm no stacked handlers; confirm Escape, outside click, and close button still work.
- Risks or open questions:
  - Impact: lingering handlers or visible lag.
  - Early warning / validation: repeated open/close while scrolling; coalesce updates per animation frame if lag appears.
  - Mitigation: single bound handler, removed on close.
- Canonical components/API contracts touched: open/close lifecycle of the shared menu in `post_analysis.js`; trigger buttons in `post_card.php` and `thread_root_card.php` unchanged.

## Stage 3
- Goal: Confirm the behavior across themes and viewports and guard against regressions.
- Dependencies: Stages 1–2.
- Expected changes: only small theme-specific margin or z-index tweaks if a theme shows overlap; otherwise none.
- Verification approach: manual pass in each shipped theme on desktop and narrow/short viewports; request a reply end to end; run the existing PHP test suite.
- Risks or open questions:
  - Impact: a theme with fixed chrome hides part of the menu.
  - Early warning / validation: theme-by-theme visual check.
  - Mitigation: shared viewport margin value rather than per-theme forks.
- Canonical components/API contracts touched: shared menu styles in `content-interactions.css`; no API or template contracts changed.
