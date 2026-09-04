# SQLite Viewer Pagination Step 2 Feature Description

## Problem

The SQLite viewer currently exposes only a bounded preview of table rows and query results. Users need to inspect subsequent portions of larger datasets while keeping the browser responsive and preserving local-only read execution.

## User stories

- As a viewer user, I want to move through table data page by page so that I can inspect rows beyond the initial preview.
- As a query author, I want to page through query results so that useful read-only queries are not limited to the first result set.
- As a user reviewing sorted data, I want pagination to remain consistent with the selected sort order so that rows are easy to compare across pages.
- As a user, I want clear current-page and boundary states so that I know whether more results are available.

## Core requirements

- Both Explore tables data and Run query results provide page navigation with bounded per-page displays.
- Navigation retrieves only the requested result page and does not require server-side SQL execution, database writes, or schema changes.
- Previous/next controls are disabled or hidden at the correct boundaries, and empty results show an understandable state.
- Changing the table, query, or sort order resets pagination to the first page; selecting a preset continues to run it automatically.
- Existing result escaping, read-only validation, row caps, horizontal scrolling, and column sort indicators remain intact.

## Shared component inventory

- **`renderRows` result renderer:** canonical renderer for table previews and query results; extend it with shared pagination controls and page-state display rather than creating separate table markup.
- **Explore tables `renderTable` flow:** existing table selection and data preview surface; reuse the shared renderer while requesting the selected table’s current page.
- **Run query `runQuery` flow:** existing preset/ad hoc query execution surface; reuse the shared renderer while retaining the active query and page state.
- **Column sorting in table data:** existing client-side sort interaction; extend its state handling so a sort change returns to page one and remains compatible with page navigation.
- **SQLite database session:** existing browser-resident read-only database; continue using it directly, with no new API or persistence layer.

## Simple user flow

1. The user loads the database and chooses a table or runs a query.
2. The viewer displays the first bounded page and its page position.
3. The user selects Next or Previous to inspect another page.
4. The user changes the query, table, or sort order, and the viewer returns to the first page.
5. At the final page, the viewer clearly indicates that no later page is available.

## Success criteria

- A table with more than one page can be navigated forward and backward without a full-page reload.
- A query returning more than one page exposes the same navigation behavior as table data.
- Each page renders no more than the configured per-page row limit, and navigation does not expose rows outside the active page.
- Changing sort order or selecting a new query/table visibly returns the display to page one.
- Focused browser/source tests cover first, middle, last, empty, sorted, and invalid-query states while preserving the existing read-only contract.
