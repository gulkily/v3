# SQLite Query Results Sorting Step 2 Feature Description

## Problem

The SQLite viewer’s query results cannot currently be sorted, while table previews can. This makes arbitrary query output harder to inspect, particularly when results span multiple pages.

## User stories

- As a viewer user, I want to sort query results by any displayed column so that I can quickly inspect values in an order useful to me.
- As a viewer user, I want to switch between ascending and descending order so that I can find either the smallest or largest values first.
- As a viewer user, I want sorting to apply across the complete query result before pagination so that page-to-page navigation remains meaningful.
- As a query author, I want the query’s original ordering preserved until I choose a sort so that authored SQL continues to behave predictably.

## Core requirements

- Query-result columns expose the same sortable-header interaction already available for table-preview data.
- Sorting applies to the complete query result before the configured page-size limit and pagination offset.
- Selecting a column toggles between ascending and descending order, with a clear active sort indicator.
- Changing the query or selecting a different preset clears the prior query sort and returns results to the query’s authored ordering.
- Existing pagination, empty-result states, read-only validation, value rendering, horizontal scrolling, and effective-query disclosure continue to work.

## Shared component inventory

- **`renderRows` result renderer:** canonical renderer for table previews and query results; extend its existing sortable-header capability to query-result calls rather than creating a second table renderer.
- **`runQuery` query execution flow:** existing preset and ad hoc query surface; retain query state and request the selected result ordering before applying pagination.
- **`renderTable` table-preview flow:** existing sortable behavior is the reference interaction and should remain compatible and unchanged for table data.
- **Query pagination controls:** existing shared pagination output associated with query results; preserve page navigation while sorting and reset to the first page after a sort change.
- **Browser-resident SQLite database:** existing local read-only execution surface; no new server endpoint, persistence layer, or schema change is required.

## Simple user flow

1. The user loads the database and runs a preset or ad hoc read-only query.
2. The viewer displays the first page with sortable column headers and the query’s original ordering.
3. The user selects a column header to sort ascending, then selects it again to sort descending.
4. The viewer re-displays the first page in the selected order and updates the sort indicator.
5. The user moves through subsequent pages and sees the same selected ordering throughout.
6. The user runs another query, and the previous query sort is cleared.

## Success criteria

- Every displayed query-result column can initiate ascending and descending sorting without changing the query text.
- For a result spanning multiple pages, all pages follow one selected column and direction; rows are not sorted independently within each page.
- Sorting resets the query result to page one and exposes the active direction through an accessible sort indicator.
- Running a new query restores its authored ordering and does not retain the prior query’s sort state.
- Existing table-preview sorting and query pagination continue to pass their current focused checks, and empty, single-page, multi-page, and invalid-query states remain understandable.
