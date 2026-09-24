# SQLite Viewer Query Catalog Step 4 Implementation Summary

## Stage 1 - Define query-file format and validation
- Changes:
  - Added `SqliteQueryCatalog` to parse metadata comments and validate one read-only `SELECT`/`WITH` statement per `.sql` file.
  - Added focused coverage for metadata ordering, trailing semicolons, and write rejection.
- Verification:
  - `php -l src/ForumRewrite/Tools/SqliteQueryCatalog.php`
  - `php -l tests/SqliteQueryCatalogTest.php`
  - Pending runner registration until the stage is committed.
- Notes:
  - The validator currently accepts only query statements beginning with `SELECT` or `WITH` and rejects embedded semicolons.

## Stage 2 - Move existing presets into canonical SQL files
- Changes:
  - Added one metadata-bearing `.sql` source for each existing viewer preset.
  - Preserved the liked+newest default query, including its joins, filters, selected fields, and ordering.
  - Added repository-catalog coverage for stable ordering and default-query parity markers.
- Verification:
  - `php tests/run.php SqliteQueryCatalogTest`
- Notes:
  - Browser generation still consumes the existing inline catalog until Stage 3.

## Stage 3 - Generate the browser query catalog
- Changes:
  - Added `scripts/build_sqlite_query_catalog.php` to generate the preset array in `public/assets/sqlite_viewer.js` from the canonical SQL files.
  - Removed browser-maintained duplicate query text while preserving the existing selector and runner contract.
- Verification:
  - `php scripts/build_sqlite_query_catalog.php`
  - `node --check public/assets/sqlite_viewer.js`
  - `php tests/run.php SqliteQueryCatalogTest`
- Notes:
  - Browser output is deterministic and remains a single viewer asset; the local-use pack is Stage 4.

## Stage 4 - Provide the local-use query pack
- Changes:
  - Extended the generator to produce `public/assets/sqlite_query_catalog.sql` from the same canonical sources.
  - Added a read-only download route and linked the pack from the SQLite viewer.
  - Kept the SQLite database schema and existing database download unchanged.
- Verification:
  - `php scripts/build_sqlite_query_catalog.php`
  - `php tests/run.php LocalAppSmokeTest::testInstanceDownloadRoutesReturnRepositoryArchivesAndSqliteDatabase`
  - Manual artifact inspection confirmed the pack contains metadata and all current presets.
- Notes:
  - The pack is a plain SQL companion file for local SQLite use; it does not add views to the database.

## Stage 5 - Add prioritized interface and statistics presets
- Changes:
  - Added all-board and liked-oldest parity presets with the interface's hidden-record, label, score, join, and ordering rules.
  - Added activity all/content views, activity counts by kind, and content totals.
  - Kept aggregate and list results bounded for browser use.
- Verification:
  - `php scripts/build_sqlite_query_catalog.php`
  - `node --check public/assets/sqlite_viewer.js`
  - `php tests/run.php SqliteQueryCatalogTest`
  - Representative catalog queries were executed against the current read model with no schema changes.
- Notes:
  - FTS, optional analysis-table queries, and unbounded exploratory queries remain deferred.

## Stage 6 - Complete verification and contributor handoff
- Changes:
  - Added contributor documentation for metadata headers, read-only query rules, and catalog generation.
  - Retained focused parser, generator, viewer, download, and artifact coverage.
- Verification:
  - `php -l src/ForumRewrite/Tools/SqliteQueryCatalog.php`
  - `php -l scripts/build_sqlite_query_catalog.php`
  - `php -l src/ForumRewrite/Application.php`
  - `node --check public/assets/sqlite_viewer.js`
  - `php scripts/build_sqlite_query_catalog.php`
  - `php tests/run.php`
  - `git diff --check`
- Notes:
  - The full suite retains four unrelated baseline failures in `LocalAppSmokeTest`: missing `pages/profile.php`, a core-route `Public key` assertion, a missing `profiles` fixture table, and an existing undefined `$css` assertion. Focused query-catalog and SQLite download tests pass.
