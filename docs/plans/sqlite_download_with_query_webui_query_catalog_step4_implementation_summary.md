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
