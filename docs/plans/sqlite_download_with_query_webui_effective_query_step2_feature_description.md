# SQLite Viewer Effective Query Step 2 Feature Description

## Problem

The Run query tool wraps user SQL for local record counts and paginated data retrieval, but the executed statements are invisible. Users need a concise way to inspect and reproduce the exact local query behavior.

## User stories

- As an advanced user, I want to see the effective SQL so that I can reproduce a viewer result in a local SQLite shell.
- As a developer, I want to inspect the pagination and count wrappers so that I can diagnose query-runner failures.
- As a query author, I want the disclosure to update when I change pages or rerun SQL so that it reflects the result currently shown.
- As a general user, I want the detail collapsed by default so that the normal query workflow stays uncluttered.

## Core requirements

- Run query provides a collapsed effective-query disclosure that clearly separates the count statement from the paginated data statement.
- The disclosure reflects the latest valid execution, including the active query text, page, limit, and offset; page navigation updates it.
- SQL is displayed as escaped, selectable text and does not change execution, read-only validation, pagination, or result rendering.
- Validation failures explain that no effective statement was executed; execution failures retain the effective SQL context and the underlying error.
- The feature remains client-side only and does not add a server API, database change, query log, or persistent storage.

## Shared component inventory

- **Run query panel:** existing canonical query input, preset selector, and Run Query action; extend this panel with the disclosure.
- **`runQuery` execution flow:** existing source of normalized SQL, count wrapper, data wrapper, pagination state, and errors; provide the disclosure with the same execution values.
- **Query status and result surface:** existing status message and result renderer; keep errors/results in place and add the detail adjacent to the query result context.
- **Shared pagination controls:** existing page navigation used by query results; update the effective-query display through the same page transitions.
- **Explore tables:** remains unchanged; its internal table-inspection statements are not part of this feature.

## Simple user flow

1. The user loads the database and runs a preset or valid SQL query.
2. The viewer displays results and a collapsed effective-query disclosure.
3. The user expands it to inspect or copy the count and data statements.
4. The user changes page or reruns the query, and the disclosure updates.
5. If validation rejects the input, the viewer reports that no effective SQL was executed.

## Success criteria

- Every successful query run exposes both effective local statements with the current page parameters.
- Moving between query-result pages changes the displayed data statement’s pagination values.
- The disclosure text can be selected and copied without executing or modifying the SQL.
- Invalid SQL and runtime failures retain understandable status context without falsely claiming successful execution.
- Existing focused query, pagination, read-only, and result-rendering tests continue to pass.
