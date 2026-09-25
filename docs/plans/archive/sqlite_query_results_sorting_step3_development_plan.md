# SQLite Query Results Sorting Step 3 Development Plan

- Scope boundary: sortable ordering for query results in the existing browser-resident SQLite viewer, applied before query-result pagination.
- Out of scope: table-preview sorting changes, server-side execution, database writes or schema changes, persistent sort preferences, and new query syntax.

## Stage 1
- Goal: Add query-result sort state and apply it to the complete result before pagination.
- Dependencies: Approved Steps 1–2; existing `runQuery` pagination and read-only query validation.
- Expected changes: Track the active query sort column and direction; extend the query execution path to preserve authored ordering by default and apply a selected result-column ordering before `LIMIT`/`OFFSET`; reset page and sort state when a new query or preset runs; keep effective-query disclosure accurate.
- Verification approach: Run unsorted, ascending, and descending multi-page queries; confirm page one resets after sorting and later pages follow the same order.
- Risks or open questions:
  - Sorting must identify result columns safely for arbitrary valid single-`SELECT` queries, including duplicate or aliased headings.
  - `NULL`, numeric, and text values need stable, understandable ordering.
- Canonical components/API contracts touched: `runQuery(page, sortColumn, sortDirection)` behavior; `isSingleSelectQuery`; existing local SQLite execution and effective-query display.

## Stage 2
- Goal: Expose query-result sorting through the shared accessible result-table headers.
- Dependencies: Stage 1; existing `renderRows` sortable-header interaction used by table previews.
- Expected changes: Enable sortable headers for the query-result `renderRows` call; pass active sort state and a sort callback; preserve the existing ascending/descending toggle, `aria-sort` state, value rendering, empty states, and pagination container behavior; keep table-preview sorting unchanged.
- Verification approach: Click each query-result header in both directions, confirm only one active indicator is shown, verify keyboard activation, and confirm table-preview headers retain current behavior.
- Risks or open questions:
  - The shared renderer must not apply client-only sorting to a truncated query page.
  - Sort callbacks must not retain state across a newly executed query or preset.
- Canonical components/API contracts touched: `renderRows(node, result, emptyMessage, maxRows, sortable, pagination)`; query-result container; existing `.sqlite-sort-button` and `aria-sort` contracts.

## Stage 3
- Goal: Add focused regression coverage and complete the feature verification.
- Dependencies: Stages 1–2.
- Expected changes: Extend existing SQLite viewer smoke/source checks for query sortable headers, sort-state wiring, page reset, effective-query ordering, invalid-query protection, and unchanged table sorting; update no schema or generated catalog assets unless verification identifies a required synchronization change.
- Verification approach: Run JavaScript syntax checks, focused PHP smoke tests, the relevant full test suite, and a manual browser walkthrough covering empty, single-page, multi-page, ascending, descending, preset, ad hoc, and invalid-query cases.
- Risks or open questions:
  - Source-contract tests may verify wiring rather than full browser interaction, so the multi-page sort behavior needs an explicit manual check.
  - Existing unrelated worktree changes must remain untouched and distinguishable from feature results.
- Canonical components/API contracts touched: `public/assets/sqlite_viewer.js`; `tests/LocalAppSmokeTest.php`; existing query-result pagination, validation, and accessibility contracts.
