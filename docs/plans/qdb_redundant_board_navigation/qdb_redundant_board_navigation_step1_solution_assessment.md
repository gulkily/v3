# QDB Redundant Board Navigation — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_redundant_board_navigation_step1_solution_assessment.md) · [Step 2](./qdb_redundant_board_navigation_step2_feature_description.md) · [Step 3](./qdb_redundant_board_navigation_step3_development_plan.md) · [Step 4](./qdb_redundant_board_navigation_step4_implementation_summary.md)

## Original Query

The qdb theme top nav already has the views and sorts we want. Please remove
the second nav bar for Latest, Top, 1337, views. Write Step 1 of
`docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

On QDB quote-listing pages, retain the dedicated header links (Latest, Top,
and 1337) and remove the redundant in-page generic board-controls navigation.
Other sites must keep their existing board controls.

## Problem

QDB renders both its purpose-built header navigation and the generic board
view/sort navigation, presenting duplicate and conflicting ways to browse
quotes.

## Options

### Option A — Hide the generic controls with QDB theme CSS

Leave the board-controls markup in place and visually suppress it only when
the QDB theme is active.

- Pros: minimal code change; preserves the shared board template.
- Cons: hides controls only for one selected theme, leaves redundant keyboard
  and assistive-technology navigation, and can expose the controls under a
  permitted non-QDB theme.

### Option B — Omit generic board controls for the QDB experience (Recommended)

Give the shared board page an experience-specific way to exclude the generic
board-controls card when rendering QDB listings, while retaining it for forum
profiles and preserving QDB header navigation.

- Pros: removes the duplicate navigation semantically and visually; remains
  correct across every permitted QDB theme; confines the behavior to QDB.
- Cons: adds a small presentation contract to the shared board rendering path.

## Recommendation

Adopt Option B. It is a viable vertical slice: a visitor opens Latest, Top, or
1337 from the QDB header and sees quote results without a second view/sort
navigation bar; non-QDB boards retain their controls. The change is
presentation-only and must not alter QDB routing, sort order, pagination, or
the header navigation links.

Waiting for "Approved Step 1" before drafting Step 2.
