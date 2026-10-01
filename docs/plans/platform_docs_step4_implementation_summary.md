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

## Stage 2 — Public index and document pages

### Changes

- Added public `/docs/` and `/docs/<path>` routes with a Docs-specific
  controller and templates. The detail page visibly renders the exact
  repository-relative source path for every document.
- Added a small safe Markdown renderer for the repository documentation's
  common structures. It escapes raw HTML, permits only safe link schemes, and
  renders headings, paragraphs, lists, block quotes, inline formatting, and
  fenced code blocks.
- Kept Docs publicly readable even if the optional members-only site mode is
  enabled, matching the open-repository boundary in the approved feature
  description.

### Verification

- `php tests/run.php PlatformDocsCatalogTest` — 3 passed.
- `php tests/run.php PlatformDocsPageTest` — 4 passed.
- `git diff --check`

### Notes

- Site-wide Docs discovery and static-release publication remain Stage 3 work.
