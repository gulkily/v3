# SQLite Viewer Query Output Column Order Step 1 Solution Assessment

## Problem Statement

The SQLite viewer’s example queries return technically useful columns, but their current order often puts IDs, timestamps, JSON fields, and internal metadata before the human-readable content users want to inspect. Because the viewer renders result columns in the order returned by each query, the examples are harder to scan horizontally and less interesting at first glance, especially on narrower screens.

The feature should make preset results more presentable and scrollable by placing meaningful content first, without changing what the queries represent or altering the read-only execution model.

## Option A: Reorder columns in the canonical SQL query files

Update the `SELECT` lists in `queries/sqlite/*.sql` so each example starts with its most useful human-readable fields, followed by dates and useful metrics, then identifiers and diagnostic/internal fields where they remain relevant.

Pros:

- Uses the existing source-of-truth and generated-catalog workflow.
- Changes the browser viewer and downloadable query pack consistently after regeneration.
- Requires no viewer rendering or database-schema changes.
- Keeps each query’s result shape explicit and easy to review in SQL.

Cons:

- The column order must be reviewed query by query.
- Users who depend on the current positional order of downloaded query results will see a presentation-level compatibility change.

## Option B: Reorder columns in the viewer with a client-side presentation map

Keep the SQL result order unchanged and add query-specific JavaScript metadata describing the preferred display order.

Pros:

- Leaves the downloadable SQL result order unchanged.
- Could support different display orders for different consumers later.

Cons:

- Duplicates query-shape knowledge outside the canonical SQL.
- Makes the browser output differ from the local query-pack output.
- Adds mapping and fallback behavior for renamed, added, or missing columns.
- Does not improve the downloaded examples that users run outside the viewer.

## Option C: Add generic viewer heuristics for column ordering

Have the renderer identify likely titles, authors, dates, counts, IDs, and JSON fields and reorder arbitrary query results automatically.

Pros:

- Could affect both presets and user-entered queries.
- Avoids maintaining per-query display metadata.

Cons:

- Heuristics can produce surprising or incorrect orders.
- Changes the meaning of user-authored result layouts.
- Makes output less predictable and harder to reproduce from the SQL.
- Still would not change the downloadable query pack.

## Recommendation

Recommend Option A: reorder the canonical preset `SELECT` lists and regenerate the browser catalog and downloadable query pack.

Use a consistent presentation priority: primary readable content first (subject, label, username, author), then relevant timestamps and activity metrics, then stable identifiers, JSON/blob-like fields, and internal provenance or approval fields. Preserve every selected field unless there is a separate decision to remove it; preserve joins, filters, `ORDER BY` clauses, limits, aliases, and query IDs.

This is the smallest change that improves the viewer’s horizontal scanning experience while keeping browser and local outputs aligned. Verification should confirm that every preset still executes and that generated assets match the updated source queries.

