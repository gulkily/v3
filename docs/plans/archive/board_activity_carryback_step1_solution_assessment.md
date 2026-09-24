# Step 1: Solution Assessment — Board/Activity Carry-Back (+ Board Score column)

**Status: Approved Step 1 (2026-09-22), scope narrowed.** The column-squeeze fix originally assessed here was split out into a separate, broader effort — see `forte_mobile_friendly_step1_solution_assessment.md`. This feature now covers only the two low-uncertainty items below.

## Problem statement
Port two confirmed-applicable, low-risk improvements from the Forte Users rebuild — relative-date list rows and a Score column — to Board and Activity.

## Scope (no options needed — both items reuse existing, proven mechanisms)
- **Relative-date rows**: swap `$timestamp` → `$relativeTimestamp` in `paned_board_thread_list.php` and `paned_activity_item_row.php` / `paned_activity_commit_row.php`. Closure already exists and is proven in Users; template-only change.
- **Board Score column**: `threads.score_total` already exists, is already populated by `ReadModelBuilder`, and is already used for default sort ordering (`Application.php:3639`). Adding a visible/sortable column follows the exact pattern the other Board columns use (`data-paned-sort-column` head button + `data-paned-sort-<col>` row attribute) — the sort JS (`paned_board_reader.js`) is already column-agnostic. No schema change, no new backend logic.

Proceeding to Step 2.
