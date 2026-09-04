# SQLite Viewer Pagination Step 3 Development Plan

- Scope boundary: shared paginated result display for table data and query results in the existing browser-resident SQLite viewer.
- Out of scope: server-side execution, database writes/schema changes, persistent caching, FTS, keyset pagination, and exact total-count queries.

## Stage 1
- Goal: Refactor the existing result renderer around shared pagination state and controls.
- Dependencies: Approved Steps 1–2; current `renderRows` table/query renderer.
- Expected changes: Define shared page state and rendering inputs; add accessible Previous/Next controls, page position text, boundary handling, and empty-page messaging without duplicating markup.
- Verification approach: Exercise the shared renderer with first, middle, last, and empty synthetic result states; confirm row limits and safe text rendering remain unchanged.
- Risks or open questions:
  - Avoid implying an exact total when the viewer intentionally does not count the full result set.
- Canonical components/API contracts touched: `renderRows`; existing table/query result containers; current result escaping and horizontal-scroll styling.

## Stage 2
- Goal: Paginate Explore tables data from the local database.
- Dependencies: Stage 1; existing table selection and schema inspection.
- Expected changes: Track the selected table’s page independently; retrieve only the active bounded page plus enough information to determine whether a next page exists; reset to page one when the table changes.
- Verification approach: Navigate a table with multiple pages forward and backward, verify first/last boundaries, and confirm empty tables remain understandable.
- Risks or open questions:
  - Tables with unusual schemas or reserved names must continue using the existing safe identifier handling.
- Canonical components/API contracts touched: `renderTable`; `populateExplorer`; browser SQLite session; `sqlite_master` table discovery.

## Stage 3
- Goal: Paginate Run query results from the local database.
- Dependencies: Stage 1; existing read-only query validation and preset auto-run behavior.
- Expected changes: Track the active SQL and result page; apply page navigation to valid ad hoc and preset queries; reset to page one for a new query or preset while retaining the existing per-page cap and query semantics.
- Verification approach: Run a multi-page query, navigate both directions, verify invalid queries still produce no database request, and confirm presets still execute immediately on selection.
- Risks or open questions:
  - Presets with their own intentional `LIMIT` remain bounded by that query’s semantics and may have fewer pages.
- Canonical components/API contracts touched: `runQuery`; `isSingleSelectQuery`; query input/selector; existing local read-only execution contract.

## Stage 4
- Goal: Make table sorting and pagination interact predictably.
- Dependencies: Stages 1–2; existing clickable table headings and sort indicators.
- Expected changes: Preserve selected sort direction across page navigation, reset to page one when the column or direction changes, and ensure each retrieved table page follows the active sort order rather than sorting only an already-truncated page.
- Verification approach: Sort ascending and descending across multiple pages, verify indicators and page resets, and confirm NULL/numeric/text comparison behavior remains understandable.
- Risks or open questions:
  - A table column may contain mixed value types or duplicate values, so navigation must remain stable enough for ordinary inspection.
- Canonical components/API contracts touched: Existing sortable `renderRows` headers; `quoteIdentifier`; table-preview query path; `aria-sort` indicators.

## Stage 5
- Goal: Complete accessibility, regression, and maintainer verification.
- Dependencies: Stages 1–4.
- Expected changes: Add focused source/browser-contract coverage for shared controls, page resets, boundaries, sorting, query errors, and result caps; update viewer copy if needed to explain page behavior.
- Verification approach: Run JavaScript syntax checks, focused PHP smoke tests, the relevant full test suite, catalog generation checks, and a manual browser walkthrough for both surfaces on a multi-page database.
- Risks or open questions:
  - The existing repository has unrelated baseline smoke failures that must remain distinguishable from pagination regressions.
- Canonical components/API contracts touched: SQLite viewer template/assets; `LocalAppSmokeTest`; existing generated query catalog and download surfaces.
