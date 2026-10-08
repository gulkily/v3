> **Feature plan:** [Step 1](./forte_mobile_stacked_panes_step1_solution_assessment.md) · [Step 2](./forte_mobile_stacked_panes_step2_feature_description.md) · [Step 3](./forte_mobile_stacked_panes_step3_development_plan.md) · [Step 4](./forte_mobile_stacked_panes_step4_implementation_summary.md)

# Forte Mobile Stacked Panes — Step 2: Feature Description

## Problem

At narrow viewport widths, Forte retains its desktop side-by-side pane layout, making the list and content panes too narrow to use. The canonical layout is shared by the Board, Activity, and Users readers, so they need one consistent mobile behavior.

## User Stories

- As a mobile Forte reader, I want the filter, list, and detail panes stacked at full width so that I can read and act on each pane without compressed columns.
- As a Forte reader on a wide screen, I want the established three-pane desktop layout preserved so that my current workflow does not change.
- As a reader moving between Board, Activity, and Users, I want their narrow-screen layouts to behave consistently so that each view remains familiar.

## Core Requirements

- At the defined narrow-screen breakpoint, stack the existing filter/folder, list, and detail panes vertically in their current order and give each the available width.
- Keep a list's primary text column readable; when its columns cannot fit, contain overflow in the list rather than squeezing the primary column or the whole page.
- Preserve existing selection, filtering, sorting, links, keyboard behavior, URL state, and data loading.
- Retain the current side-by-side three-pane presentation above the breakpoint.
- Apply the shared behavior to `/forte`, `/forte/activity/`, and `/forte/users/`.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: a reader opens any supported Forte three-pane route on a narrow viewport.
- End-to-end outcome: the reader can use the full-width filter, list, and detail panes in sequence; choosing a filter or list item still updates the existing detail pane.
- Recovery: resizing, rotating, or reloading restores the appropriate responsive layout without losing the route's existing state; no new error or fallback flow is introduced.
- Release condition: narrow and desktop checks pass for Board, Activity, and Users, including readable primary list columns and no layout-caused page-level horizontal overflow.

## Risks

- Shared layout regression — impact: all three readers could be affected; earliest validation: render each route at narrow and desktop widths; mitigation: extend the canonical layout only and test every consumer before Step 3.
- Usable vertical space and independent scrolling — impact: a pane could obscure another or trap navigation; earliest validation: mobile viewport interaction check; mitigation: verify each pane remains reachable and scrollable after selection changes.
- Dense list columns — impact: a title or label could still collapse at phone width; earliest validation: inspect the primary list column at the known 400px viewport; mitigation: keep overflow contained in the affected list and confirm the primary text remains readable.

## Shared Component Inventory

- `public/assets/forte.css` paned-layout rules: extend this canonical styling; do not create a separate mobile reader.
- `templates/pages/forte_board.php`, `forte_activity.php`, and `forte_users.php`: reuse their existing three-pane structure and ordering; no new page or data surface is needed.
- `paned_board_reader.js`, `paned_activity_reader.js`, and `paned_users_reader.js`: retain their existing selection, URL, and loading behavior; the activity reader's refreshed layout must continue to receive the same responsive presentation.
- Existing Board, Activity, and Users endpoints: reuse unchanged; no API or schema work is required.

## Simple User Flow

1. A reader opens Board, Activity, or Users on a narrow screen.
2. The filter/folder pane appears first, followed by the usable list and detail panes.
3. The reader filters or selects an item and sees the existing detail behavior in the full-width detail pane.
4. The reader rotates, resizes, or reloads and retains the route's current reader state in the appropriate layout.

## Success Criteria

- At a 400px-wide viewport, all supported routes show the three panes vertically with no compressed side-by-side pane.
- At that viewport, Board Subject and Activity Label no longer collapse to their previously observed 16px and 31px widths, respectively.
- Any necessary list overflow is confined to its list pane; the page itself does not gain horizontal scrolling from the reader layout.
- At desktop width, each route retains its current three-pane side-by-side layout and normal selection behavior.
