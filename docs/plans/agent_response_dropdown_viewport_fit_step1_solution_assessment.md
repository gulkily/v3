> **Feature plan:** [Step 1](./agent_response_dropdown_viewport_fit_step1_solution_assessment.md) · [Step 2](./agent_response_dropdown_viewport_fit_step2_feature_description.md) · [Step 3](./agent_response_dropdown_viewport_fit_step3_development_plan.md) · [Step 4](./agent_response_dropdown_viewport_fit_step4_implementation_summary.md)

# Agent Response Dropdown Viewport Fit – Solution Assessment

## Original Query
The request agent response dropdown does not always fully display in-page. It should go up and above the link if below is not enough viewport space. Other ways of mitigating it? It should scroll along with its parent button.

## Understood Intent
The mode menu opened from the "request agent reply" button should always be fully readable and usable, and it should stay attached to its button while the page scrolls. Today the menu is a single fixed-position element placed once on open: it already flips above the button when there is no room below, but it does not follow the button on scroll or resize, and it can still be clipped when neither side has enough room.

## Problem Statement
The agent response mode menu can be cut off by the viewport and drifts away from its button when the page scrolls.

## Option A: Keep the floating menu, make placement continuous
Keep the existing body-level fixed menu and have it recompute its position on scroll and resize so it stays glued to its button, flipping above or below as space allows. Cap its height to the space actually available on the chosen side (scrolling inside the menu if needed) instead of the whole viewport, and close it if the button scrolls out of view.

- Pros: smallest change; reuses the current menu, markup, and styling
- Pros: fixes all three reported symptoms (clipping, flip, scroll-follow) in one place
- Cons: needs scroll/resize listeners and per-frame repositioning logic
- Cons: very short viewports still mean a scrollable menu

## Option B: Anchor the menu inside the button's container
Render the menu as a child of the post's action row (absolutely positioned) so it scrolls with its parent natively, flipping above or below with a small placement rule.

- Pros: scrolls with the button for free, no scroll listeners
- Cons: ancestors with overflow clipping or collapsed action rows (hover/focus-within reveal) can clip or hide it
- Cons: the menu is currently one shared instance; per-post menus change markup and a11y wiring, and affect every theme

## Option C: Switch to a bottom sheet / modal on constrained space
Keep the floating menu where it fits, but on narrow or short viewports present the choices as a pinned bottom sheet or centered dialog that never needs anchoring.

- Pros: always fully visible and easy to tap on mobile
- Pros: sidesteps scroll-follow and flip logic in the constrained case
- Cons: two presentations to build, style, and test across themes
- Cons: breaks the "attached to its button" feel the request asks for

## Recommendation
Option A. It is a single vertical slice: a viewer opens the menu from any post at any scroll position, sees every choice, scrolls with it still attached, and picks a mode as today. It touches only the existing menu script and styles. Options B and C can be follow-ups if A proves insufficient on very small screens.

**Awaiting approval:** reply `Approved Step 1` to proceed to Step 2.
