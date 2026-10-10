> **Feature plan:** [Step 1](./agent_response_dropdown_viewport_fit_step1_solution_assessment.md) · [Step 2](./agent_response_dropdown_viewport_fit_step2_feature_description.md) · [Step 3](./agent_response_dropdown_viewport_fit_step3_development_plan.md) · [Step 4](./agent_response_dropdown_viewport_fit_step4_implementation_summary.md)

# Agent Response Dropdown Viewport Fit – Feature Description

## Problem
The mode menu opened from the "request agent reply" button is placed once on open, so it can be clipped by the viewport and does not stay attached to its button when the page scrolls or resizes.

## User Stories
- As a reader, I want every response mode in the menu to be fully visible so that I can choose one without the menu being cut off.
- As a reader, I want the menu to open above the button when there is not enough room below so that it stays on screen.
- As a reader, I want the menu to move with its button as I scroll so that it never appears detached from the post I am acting on.
- As a mobile reader, I want the menu to remain usable on short or narrow screens so that I can still request a reply.

## Core Requirements
- The menu opens on the side of the button (below or above) with more available room, preferring below.
- While open, the menu stays anchored to its button on page scroll and window resize.
- The menu never exceeds the space available on its chosen side; if its content is taller, the menu itself scrolls.
- If the button scrolls out of the viewport, the menu closes.
- Existing behavior is unchanged: choices, keyboard/Escape/outside-click closing, focus handling, and request flow.

## Delivery Scope
- Work type: application change (front-end behavior and styling of the existing menu only; no server, API, or data changes)

## Completion Boundary
- Normal entry: a reader clicks "request agent reply" on a post or thread root at any scroll position.
- End-to-end outcome: the full menu is visible and attached to its button, and picking a mode submits the request as it does today.
- Recovery: closing the menu (Escape, outside click, close button, or button leaving the viewport) returns the page to its prior state; no data is affected.
- Release condition: verified on desktop and a narrow/short viewport in each shipped theme.

## Risks
- **Scroll repositioning feels janky or leaks listeners:** impact is visual lag or lingering handlers. Validate early by scrolling with the menu open and closing it repeatedly; mitigate by attaching handlers only while open and removing them on close.
- **Very short viewports leave too little room on either side:** impact is a tiny, awkward menu. Validate by testing at small window heights; mitigate with a sensible minimum height and internal scrolling.
- **Theme styling differences (fixed headers, themed menu chrome):** impact is the menu overlapping or hiding behind theme elements. Validate by checking each theme; mitigate by sharing one placement rule and adjusting the viewport margin only if needed.

## Shared Component Inventory
- Agent response mode menu (`post_analysis.js` placement and `content-interactions.css` styling): the canonical, single shared instance; extend it, no new component.
- Trigger buttons in `post_card.php` and `thread_root_card.php`: reused unchanged.
- Mode catalog partial (`agent_response_mode_catalog.php`): unchanged.

## Simple User Flow
1. Reader scrolls to a post and clicks "request agent reply".
2. The menu appears fully visible, below the button or above it if below lacks room.
3. Reader scrolls; the menu follows the button (or closes if the button leaves the viewport).
4. Reader picks a mode or dismisses the menu; the request proceeds or the page returns to normal.

## Success Criteria
- At any scroll position and at viewport heights down to a small phone landscape size, no menu choice is clipped or unreachable.
- With the menu open, scrolling or resizing keeps it within the viewport and visually attached to its button.
- No regression to existing open/close/keyboard behavior or the reply request.

**Awaiting approval:** reply `Approved Step 2` to proceed to Step 3.
