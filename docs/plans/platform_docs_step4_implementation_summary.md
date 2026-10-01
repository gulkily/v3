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

## Stage 3 — Public discovery and static release support

### Changes

- Added Docs to the public header navigation and the existing Tools discovery
  registry, so it is both a first-class destination and reachable from the
  established tools index.
- Added Docs index and catalogued-document artifacts to the standard static
  release and shared-refresh flows. The front controller safely maps those
  routes to their pre-rendered artifacts; uncatalogued documents continue to
  fall through to the public application route.
- Added a release smoke test that builds a clean static artifact set, checks
  every catalog entry, and serves the index and a document through the front
  controller's static path.

### Verification

- `php tests/run.php PlatformDocsPageTest` — 5 passed.
- `php tests/run.php PlatformDocsStaticTest` — 1 passed.
- `php tests/run.php LocalAppSmokeTest::testBuildStaticCommandReportsProgressAndArtifactSummary LocalAppSmokeTest::testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout` — 3 passed.
- `git diff --check`

### Notes

- The Stage 4 boundary tests can now concentrate on regression coverage; the
  public routes, navigation, and normal static publication path are complete.

## Stage 4 — Public-boundary regression coverage

### Changes

- Added regression coverage for oversized documents and docs-directory
  symlinks that resolve outside the public root.
- Added coverage that Docs remains public under the optional members-only
  feature flag and that an invalid path cannot be served by a static release.
- Kept the focused route, discovery, Markdown-safety, catalog, and static
  release tests in the normal PHP test runner.

### Verification

- `php tests/run.php PlatformDocsCatalogTest` — 4 passed.
- `php tests/run.php PlatformDocsPageTest` — 6 passed.
- `php tests/run.php PlatformDocsStaticTest` — 1 passed.
- `php tests/run.php` — Docs coverage passed. Two pre-existing browser-signing
  tests remain failing because their Node helper calls the missing
  `state.resolveCreatePreparedPost`; the runner classifies both as long-standing
  failures since 2026-09-30, and this feature does not change
  `public/assets/browser_signing.js`.
- `git diff --check`

### Outcome

- Public platform documentation is available at `/docs/`, visibly identifies
  every document's repository source path, is discoverable from navigation and
  Tools, and is included in normal static releases.

## Follow-up — Transparency overview and Tools discovery

### Changes

- Moved the Docs destination out of the global header; it remains discoverable
  from the Tools page, where the platform-oriented destinations already live.
- Expanded the Docs landing page with a visible transparency overview covering
  Git-backed public records, rebuildable derived views, browser-held OpenPGP
  private keys, and explicit private-runtime boundaries.
- Added a catalogued `Public Architecture and Trust Model` guide, alongside
  canonical-record and identity-bootstrap references.

### Verification

- `php tests/run.php PlatformDocsCatalogTest` — 4 passed.
- `php tests/run.php PlatformDocsPageTest` — 6 passed.
- `php tests/run.php PlatformDocsStaticTest` — 1 passed.
- Static-build and shared-refresh progress smoke tests — 2 passed.
