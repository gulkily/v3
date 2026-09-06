# Step 2: Feature Description — SQLite Query Panel Side-by-Side Layout

## Problem
On wide screens, the SQLite viewer's query editor and results are stacked vertically, wasting available horizontal space and forcing extra scrolling to see both at once.

## User Stories
- As a user running queries on a wide screen, I want the editor and results shown side-by-side so I can see both without scrolling.
- As a user on a narrow screen, I want the current stacked layout preserved so nothing breaks on small viewports.

## Core Requirements
- On wide viewports, the query editor and results render side-by-side within the query panel card.
- On narrow viewports, the layout stays stacked (unchanged from today).
- The layout state is driven by a data attribute (not a raw media query alone) so a future front-end toggle can override it without rework.
- No new user-facing toggle control ships with this feature.
- No regression to existing query panel behavior (running queries, pagination, effective-query disclosure, result scrolling).

## Shared Component Inventory
- `[data-role="sqlite-query-panel"]` in `templates/pages/sqlite_viewer.php` is the sole existing surface rendering the editor + results together; no other page renders this pairing.
- This feature extends that existing panel's markup/CSS in place — no new component is introduced.

## Simple User Flow
1. User opens the SQLite viewer on a wide screen.
2. User types/edits a SQL query in the editor.
3. User runs the query.
4. Results appear beside the editor (not below it), with pagination and effective-query disclosure still visible.
5. On a narrow screen, the same flow renders editor-above-results as it does today.

## Success Criteria
- On viewport widths at/above the chosen breakpoint, editor and results render side-by-side with no horizontal overflow of the page.
- On viewport widths below the breakpoint, layout is visually unchanged from current behavior.
- All existing query panel functionality (run query, pagination, effective-query detail) works identically in both layouts.
