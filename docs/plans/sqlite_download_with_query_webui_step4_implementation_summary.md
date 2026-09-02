# SQLite Download With Query Web UI Step 4 Implementation Summary

## Stage 1 - Route and viewer shell
- Changes:
  - Added `/tools/sqlite/` and `/tools/sqlite` GET routes.
  - Added the SQLite Viewer entry to the shared Tools launcher and tool sub-navigation.
  - Added the initial viewer page shell with read-only/local execution copy, published database link, and runtime placeholder.
  - Added a LocalAppSmokeTest covering route rendering, active navigation, source link, and Tools discovery.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testSqliteViewerRouteUsesToolsShellAndPublishedSource`
  - Result: `PASS`; all tests passed.
- Notes:
  - The load control remains disabled until the browser SQLite runtime is added in Stage 2.

## Stage 2 - Browser database loading
- Changes:
  - Vendored the pinned `sql.js` 1.13.0 browser runtime and WASM asset.
  - Added page-specific loading JavaScript that fetches `/downloads/read_model.sqlite3`, opens it in a browser session, and reports loading, success, download, runtime, and invalid-database failures.
  - Enabled the viewer’s load control and loaded the runtime assets through the existing fingerprinted asset pipeline.
  - Added smoke coverage for the local runtime assets and database source contract.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testSqliteViewerLoadsLocalRuntimeAssets`
  - Result: `PASS`; all tests passed.
- Notes:
  - The database is currently held only in the page’s in-memory SQLite session; no persistent browser cache was added.

## Stage 3 - Schema explorer
- Changes:
  - Added a post-load table selector and schema explorer surface.
  - Added browser-side inspection of user tables, columns, and up to 20 rows per preview.
  - Added empty and inspection-error states with DOM text rendering for database values.
  - Added smoke coverage for the explorer markup and bounded inspection contract.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testSqliteViewerIncludesSchemaExplorerContract`
  - Result: `PASS`; all tests passed.
- Notes:
  - The explorer discovers tables from SQLite metadata and does not assume only the current read-model table list.

## Stage 4 - Preset and read-only query runner
- Changes:
  - Added a viewer-owned preset catalog with obvious post, thread, profile, and activity queries.
  - Added preset selection, SQL editing, local execution, result rendering, and query status feedback.
  - Enforced a conservative one-statement `SELECT`-only contract in the browser; no query endpoint was added.
  - Added smoke coverage for the query controls, preset catalog, and read-only validation contract.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testSqliteViewerIncludesPresetReadOnlyQueryContract`
  - Result: `PASS`; all tests passed.
- Notes:
  - Full-text search, `WITH` queries, PRAGMAs, writes, and multi-statement input remain outside V1.

## Stage 5 - Result limits and interaction polish
- Changes:
  - Capped table previews at 20 rows and query results at 50 rows with visible truncation feedback.
  - Wrapped result tables in horizontally scrollable containers and styled the SQL input for technical use.
  - Added clear no-results and query-error handling while preserving text-only DOM rendering for values.
  - Prevented duplicate preset options/listeners when the database is reloaded.
  - Added smoke coverage for result caps and responsive result containers.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testSqliteViewerCapsAndScrollsResultSurfaces`
  - Result: `PASS`; all tests passed.
- Notes:
  - Result limits are enforced in the browser query wrapper and renderer; persistent caching remains out of scope.

## Stage 6 - Integration and regression verification
- Changes:
  - Completed the shared Tools integration, route/source contracts, local asset fingerprinting, and stage-specific smoke coverage.
  - Corrected the query-contract smoke assertion to match the capped query wrapper.
- Verification:
  - `node --check public/assets/sqlite_viewer.js`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/LocalAppSmokeTest.php`
  - Five SQLite viewer smoke tests covering route, runtime loading, schema exploration, preset queries, and result caps.
  - Result: all feature-specific checks passed.
  - Full `php tests/run.php` was also run; three unrelated pre-existing `LocalAppSmokeTest` failures remain in approval/core-route fixture coverage (`testInjectApprovalScriptApprovesExistingUser`, `testApplicationRendersCoreRoutes`, and `testBootstrapPostShowsUnavailablePublicKeyWhenProfileKeyIsEmpty`).
- Notes:
  - Implementation remains client-side and read-only. FTS, persistent browser caching, server-side SQL, and writes were not added.
  - Post-stage Chromium verification found and corrected the viewer-root scope bug that kept the sibling explorer/query panels hidden.

## Stage 6 - Viewer reveal correction
- Changes:
  - Moved the viewer root marker to the wrapping section so the load script can access the explorer and query sibling cards.
- Verification:
  - Real Chromium click verification against `/tools/sqlite/` confirmed database load success, visible explorer and query panels, 12 discovered tables, and four preset queries.
  - Re-ran the route, schema, and preset-query smoke tests; all passed.
- Notes:
  - This is a corrective follow-up to Stage 6 and does not add feature scope.

## Post-completion UI adjustment - full-width explorer tables
- Changes:
  - Made the Explore tables card explicitly use the full available content width.
  - Made each explorer/result table use an independently scrollable horizontal container while retaining a full-width minimum.
- Verification:
  - Re-ran `LocalAppSmokeTest::testSqliteViewerCapsAndScrollsResultSurfaces`; result: `PASS`.
  - Rechecked the viewer CSS and JavaScript syntax; no errors reported.
- Notes:
  - This is a presentation-only adjustment; query behavior and data limits are unchanged.

## Post-completion UI adjustment - query-first layout
- Changes:
  - Moved the query runner before the table explorer in the viewer flow.
  - Preselected and prefilled the first preset, Recent posts, after the database loads; execution remains user-triggered.
- Verification:
  - Re-ran the viewer preset and result-surface smoke tests; both pass.
  - Added coverage confirming query markup precedes the table explorer markup.
- Notes:
  - No query scope, result limit, or database behavior changed.

## Post-completion query adjustment - default liked/newest board view
- Changes:
  - Added a complete `Board: Liked + Newest` preset matching the board’s root-post/profile joins, selected fields, hidden identity filtering, like-label and non-negative-score constraints, pinned-first ordering, and newest tie-break ordering.
  - Made this board-view preset the default selected and prefilled query.
- Verification:
  - Browser-level verification selected the new preset and executed it against the published database, returning 50 rows under the existing viewer cap.
  - Re-ran the preset-query and query-first layout smoke tests; all passed.
- Notes:
  - The preset intentionally has no query-level limit so the viewer’s existing 50-row safety wrapper remains the only result cap.

## Post-completion UI adjustment - full-width query modules
- Changes:
  - Renamed the query module heading to `Run query`.
  - Made both the Run query and Explore tables cards span the viewport width, with their result regions retaining independent horizontal scrolling.
- Verification:
  - Re-ran the preset-query and result-surface smoke tests; both pass.
  - Rechecked JavaScript syntax and the scoped full-width CSS contract.
- Notes:
  - This is a presentation-only adjustment; query semantics and result limits are unchanged.

## Post-completion UI adjustment - viewport gutters
- Changes:
  - Reduced both full-width query modules to `calc(100vw - 2rem)` and added matching 1rem side gutters to prevent page-level horizontal overflow.
- Verification:
  - Re-ran `LocalAppSmokeTest::testSqliteViewerCapsAndScrollsResultSurfaces`; result: `PASS`.
- Notes:
  - Result-table horizontal scrolling remains local to the result containers.
