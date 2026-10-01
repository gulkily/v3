# Public Platform Docs — Step 4 Implementation Summary

## Stage 1 — Documentation root and curated catalog

### Changes

- Added `PlatformDocsCatalog` as the single policy for public documentation:
  only Markdown files beneath `docs/` may resolve, paths cannot traverse out of
  that root, symlink escapes are rejected, and documents over 1 MiB are not
  served.
- Added a curated discovery catalog for extension, architecture, CLI, and
  operations guidance. The catalog is deliberately not an access gate: any
  other safe Markdown document beneath `docs/` remains eligible for a future
  document route.
- Added focused catalog/path-policy coverage to the normal PHP test runner.

### Verification

- `php -l src/ForumRewrite/Docs/PlatformDocsCatalog.php`
- `php -l tests/PlatformDocsCatalogTest.php`
- `php tests/run.php PlatformDocsCatalogTest` — 3 passed.
- `git diff --check`

### Notes

- Public document rendering and routes remain Stage 2 work. This stage only
  establishes the reusable discovery and boundary contract.
