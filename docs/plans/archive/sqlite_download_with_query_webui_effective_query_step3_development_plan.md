# SQLite Viewer Effective Query Step 3 Development Plan

- Scope boundary: a client-side, collapsed disclosure for the exact count and paginated data statements used by Run query.
- Out of scope: table-inspection SQL, server APIs, query logging, persistence, database changes, and effective-SQL download/export.

## Stage 1
- Goal: Add the collapsed effective-query presentation surface.
- Dependencies: Approved Steps 1–2; existing Run query panel and result/status layout.
- Expected changes: Add an accessible collapsed disclosure with distinct count-query and data-query sections, selectable SQL text, and empty/initial copy that does not imply a query has run.
- Verification approach: Render checks confirm the disclosure is present, collapsed by default, labeled clearly, and does not alter existing query controls or result layout.
- Risks or open questions:
  - Long SQL must remain readable without causing unwanted page-wide overflow.
- Canonical components/API contracts touched: SQLite viewer query panel; existing result/status styling; no server or database contract.

## Stage 2
- Goal: Capture and display the exact effective statements for each query run.
- Dependencies: Stage 1; existing `runQuery` normalization, count execution, data execution, and pagination state.
- Expected changes: Define query-display state and helpers such as `renderEffectiveQuery(countSql, dataSql)`; populate it from the same normalized SQL, page size, page, and offset values used for local execution; update it on page navigation and reruns.
- Verification approach: Compare displayed count/data SQL with the statements executed for a preset, freehand query, first page, and later page; confirm the user SQL is unchanged.
- Risks or open questions:
  - Count and data statements can diverge when a query contains its own limit or ordering, so both must be shown rather than summarized as one statement.
- Canonical components/API contracts touched: `runQuery`; shared query pagination callbacks; browser SQLite execution path.

## Stage 3
- Goal: Keep effective-query context accurate for validation and runtime failures.
- Dependencies: Stage 2 query-display state and existing error handling.
- Expected changes: Distinguish “no statement executed” validation failures from failures after effective SQL construction; retain the latest attempted statements where available while preserving the underlying SQLite error.
- Verification approach: Exercise empty input, write/multi-statement input, malformed SQL, count failures, and successful execution; verify each status and disclosure state is truthful.
- Risks or open questions:
  - A validation failure has no effective wrapper statement and should not display stale SQL as if it belonged to the failed attempt.
- Canonical components/API contracts touched: `isSingleSelectQuery`; `setQueryStatus`; query error/result surfaces.

## Stage 4
- Goal: Complete regression, accessibility, and maintainer verification.
- Dependencies: Stages 1–3.
- Expected changes: Add focused source/render tests for disclosure structure, escaping, synchronization, pagination parameters, collapsed defaults, and failure states; document the feature’s local-only behavior.
- Verification approach: Run PHP syntax checks, JavaScript syntax checks, focused SQLite viewer tests, the relevant full suite, and a manual browser pass covering preset/freehand runs and page navigation.
- Risks or open questions:
  - Existing unrelated smoke-test failures must remain distinguishable from effective-query regressions.
- Canonical components/API contracts touched: `LocalAppSmokeTest`; SQLite viewer template/assets; existing read-only and pagination contracts.
