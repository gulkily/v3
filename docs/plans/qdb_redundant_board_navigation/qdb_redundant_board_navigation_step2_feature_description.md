# QDB Redundant Board Navigation — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_redundant_board_navigation_step1_solution_assessment.md) · [Step 2](./qdb_redundant_board_navigation_step2_feature_description.md) · [Step 3](./qdb_redundant_board_navigation_step3_development_plan.md) · [Step 4](./qdb_redundant_board_navigation_step4_implementation_summary.md)

## Problem

QDB listing pages render generic All/Liked and Newest/Oldest/Top board controls
below a header that already provides QDB’s intended Latest, Top, and 1337
navigation. The redundant controls confuse browsing and do not belong to the
QDB experience.

## User Stories

- As a QDB visitor, I want quote listings to show one clear navigation system
  so that I can choose Latest, Top, or 1337 without duplicate controls.
- As a forum visitor, I want the standard board controls to remain available
  so that existing forum filtering and sorting continue to work.

## Core Requirements

- QDB Latest, Top, and 1337 listing pages must omit the generic in-page
  board-controls navigation.
- QDB’s header links and their active state must remain available and correct.
- QDB routes, quote selection, ordering, pagination, reactions, and footer
  must remain unchanged.
- Non-QDB board pages must continue to render their existing board controls.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: a visitor opens `/latest`, `/top`, or `/leetness` on QDB.
- End-to-end outcome: the page displays its intended quote results and header
  navigation without a second generic controls bar.
- Recovery: switching to a non-QDB profile restores standard board controls;
  QDB links continue to provide the supported listing choices.
- Release condition: focused rendering coverage proves the QDB omission and
  non-QDB preservation, and the relevant test suite passes.

## Risks

- Shared-template regression: removing controls globally would affect forum
  boards. Validate a non-QDB board render first; scope the omission to QDB.
- Theme-only suppression: a CSS solution could leave hidden navigation in the
  accessibility tree or reappear under another allowed theme. Validate the
  rendered markup, not only visual styling.
- Listing regression: changing the QDB render path could disturb pagination or
  quote cards. Validate all three QDB listing routes retain their results and
  header links before Step 3.

## Shared Component Inventory

- `templates/pages/board.php`: canonical shared board page that currently
  renders generic controls; extend it with QDB-specific presentation input.
- `src/ForumRewrite/Http/BoardPageController.php`: supplies shared board data
  and recognizes QDB through its policy; reuse it to pass the presentation
  choice rather than fork listing routes.
- `src/ForumRewrite/Qdb/QdbPresentation.php` and `templates/partials/nav.php`:
  canonical QDB header navigation; reuse unchanged.
- `src/ForumRewrite/Http/BoardViewOptions.php`: standard forum controls;
  retain unchanged for non-QDB boards.

## Simple User Flow

1. A visitor selects Latest, Top, or 1337 from the QDB header.
2. The corresponding quote listing renders with its existing results,
   pagination, and QDB header navigation.
3. No generic All/Liked or Newest/Oldest/Top controls bar appears below it.

## Success Criteria

- Rendered QDB `/latest`, `/top`, and `/leetness` pages contain no
  `board-controls-nav` markup.
- Each rendered QDB listing retains the header links for Latest, Top, and
  1337, with the selected route marked active.
- A rendered non-QDB board still contains `board-controls-nav` markup.
- Existing relevant automated tests pass.

Waiting for "Approved Step 2" before drafting Step 3.
