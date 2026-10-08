> **Feature plan:** [Step 1](./forte_mobile_stacked_panes_step1_solution_assessment.md) · [Step 2](./forte_mobile_stacked_panes_step2_feature_description.md) · [Step 3](./forte_mobile_stacked_panes_step3_development_plan.md) · [Step 4](./forte_mobile_stacked_panes_step4_implementation_summary.md)

# Forte Mobile Stacked Panes — Step 3: Development Plan

## Completion Contract

- Normal entry: a reader opens `/forte`, `/forte/activity/`, or `/forte/users/` at the defined narrow width.
- End-to-end outcome: the existing filter/folder, list, and detail panes appear vertically in order at full width; selection, filtering, sorting, and detail loading continue to work.
- Required recovery: reload, orientation/viewport change, and Activity's refreshed layout retain existing route state and use the correct responsive layout.
- Deployment/external verification: no deployment, API, schema, or migration work is required; verify the fingerprinted Forte stylesheet in a local browser at 400px and desktop widths.
- Release condition: all three routes meet Step 2's narrow-width criteria without layout-caused page-level horizontal scrolling and retain desktop behavior.

## Key Risks

- **High risk: shared-layout usability regression.** Impact: a pane could be hidden or unusable on Board, Activity, or Users. Early validation: render all three routes at 400px immediately after the shell change. Mitigation: extend the one canonical layout and preserve its DOM order and independent pane scrolling.
- **High risk: list-column collapse.** Impact: the primary subject/label can remain unreadable despite vertically stacked panes. Early validation: measure Board Subject and Activity Label at 400px. Mitigation: give each affected list a contained overflow path and a readable row-width floor.
- Activity refresh regression. Impact: dynamically refreshed Activity markup could miss the list behavior. Early validation: change Activity view and load more after the narrow-screen change. Mitigation: add any needed hook to the server-rendered list partial so initial and refreshed markup match.

## Stage 1

- Goal: Make the canonical Forte three-pane shell stack vertically at the agreed narrow-screen breakpoint.
- Dependencies: Approved Step 2; the existing shared Board, Activity, and Users markup.
- Expected changes: Add scoped responsive styling for the shared pane shell, folder/filter pane, and nested list/detail container; preserve the current desktop rules and markup order.
- Verification approach: At 400px, directly load Board, Activity, and Users; confirm full-width, ordered panes and no layout-caused page-level horizontal overflow. Repeat at desktop width to confirm the side-by-side layout remains.
- Risks or open questions:
  - Impact: fixed-height reader chrome can leave a stacked pane unreachable.
  - Early warning / validation: inspect and scroll every pane after a selection change at mobile height.
  - Mitigation: retain independent pane scrolling and bounded pane sizing in the responsive shell.
- Canonical components/API contracts touched: `public/assets/forte.css` shared paned-layout rules; existing `forte_board.php`, `forte_activity.php`, and `forte_users.php` markup is reused; no API contract changes.

## Stage 2

- Goal: Keep dense Board and Activity list rows readable within the mobile stack and complete regression verification.
- Dependencies: Stage 1's responsive shell.
- Expected changes: Extend the established contained-list-overflow pattern to Board and Activity, adding a server-rendered Activity list scope hook if needed; leave Users' existing contained-overflow behavior intact.
- Verification approach: At 400px, confirm Board Subject and Activity Label exceed their former 16px and 31px widths, respectively, and horizontal overflow stays inside the list. Exercise Board filter/selection, Activity view change/load-more, and Users category/selection; repeat the three routes at desktop width.
- Risks or open questions:
  - Impact: fixed columns may no longer align with their headers or may cause page-level overflow.
  - Early warning / validation: inspect headers and rows while horizontally scrolling an affected list.
  - Mitigation: apply the same width floor to each list's header and rows, scoped per reader.
  - Impact: Activity's replacement layout may omit the scope hook after a view change.
  - Early warning / validation: switch Activity views before checking the list width.
  - Mitigation: source the hook from the shared Activity list partial used for initial and refreshed markup.
- Canonical components/API contracts touched: `public/assets/forte.css`; `templates/partials/paned_board_thread_list.php` and `paned_activity_item_list.php` list-pane hooks; `paned_board_reader.js`, `paned_activity_reader.js`, and `paned_users_reader.js` are verified unchanged; no endpoint or schema changes.
