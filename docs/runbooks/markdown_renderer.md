# Documentation library, rendering and recovery

Existing Platform Docs links and direct `/docs/...md` URLs use
`PublicMarkdownRenderer` for both PHP responses and generated HTML. PHP 8.1+
and mbstring are required. Deploy the complete application, including
`third_party/markdown/composer.json`, `composer.lock`, `NOTICES.md` and the
entire production `vendor/` tree. No Composer installation or package download
is needed on the serving host. Do not upload only the renderer PHP file.

The isolated runtime pins league/commonmark 2.10.4. Maintainer update steps and
license selections are in [NOTICES](../../third_party/markdown/NOTICES.md).
Retain all upstream licenses. Make reviewed dependency updates in a development
checkout; reproduce the production tree from its lock, run Composer
`check-platform-reqs` and `audit`, and run the focused and copied-package checks
below. Commit the entire changed bundle together.

## Rendering boundary

Core Markdown and GFM tables, nested/loose lists, disabled task checkboxes,
references, emphasis and strikethrough are supported. Heading IDs are unique per
document. HTTP(S), email and same-document fragments are allowed; relative
documentation links resolve inside the library boundary below, including root
documents and supported assets. Realpath, file-size and extension checks apply;
symlinked sources and encoded structural traversal are rejected. Unsupported files
and images retain “source unavailable” or “image unavailable” labels.

Raw HTML is escaped, comments are hidden, and the existing HTML logo has an
escaped alternative. Mermaid remains labeled source. Front matter remains
escaped inside collapsed Document metadata; embedded presentation CSS is never
applied. Syntax highlighting, diagrams, slideshows and arbitrary image loading
are outside this renderer's scope. Tables and code have named keyboard-accessible
scroll regions; JavaScript is unnecessary for reading or following links.

## Library inventory and search

Tools → Platform Docs follows the deployed repository folders automatically;
there is no separately maintained folder catalog. Deploy the complete `docs/`
tree and root `README.md`, `CONTRIBUTING.md`, `AGENTS.md` and `BLESSING.md`.
The curated entries in `PlatformDocsCatalog::entries()` supply the landing page's
topic sections (title, topic, one-line description), not eligibility. To feature a
guide, add an entry there; topics appear in first-listed order, and adjacent
single-guide topics share a row on wide screens. No template edit is needed.

The library includes Markdown, TXT, CSV, JSON, HTML and PDF under `docs/`,
configuration/example files under `docs/examples/`, and `docs/railspike/LICENSE`.
Hidden/control files, other executable formats, unrelated root files and private
instance directories are excluded. Examples are public documentation; live
credentials belong in the instance's private configuration, outside this tree.
Root documents use `/docs/_project/<filename>`; existing `/docs/...md` URLs stay
valid. Non-Markdown text/HTML is escaped in previews. Downloads use attachment
responses with `application/octet-stream` and `nosniff`; examples never execute.
PDF and files above the 1 MiB preview limit offer downloads. PDF binary contents
and oversized file bodies are not indexed; their titles and paths remain searchable.

`/docs/` without query parameters is the curated landing page (intro, How it
works, topics, collection quick links). Any of `q`, `folder`, `category`, `group`
or `page` opens the results view at the same URL. Search covers titles, paths,
headings and available document text, with folder, category and collection
filters and 40-result pages. Categories are case-insensitive slugs (legacy
`category=Architecture` URLs still match); filter counts are library-wide.
Choosing a filter replaces other filters and keeps the search text. Folder filtering includes
subfolders. Current guidance ranks before plans/history and vendored workflow;
being listed under plans does not assert that a plan is obsolete. Search includes
all collections unless filtered. Query URLs preserve results when returning from
a reader; JavaScript also carries them through related-document links. Dynamic
search/browsing works without JavaScript. Static releases prebuild every browse
page (all documents, each collection, category and folder, every page) under
`/docs/_browse/`, so browsing and filtering work there without JavaScript too;
search text and combined filters are served `/docs/_search.html`, which
JavaScript fills from `/docs/_search.json` and which explains, without
JavaScript, that search needs it. Index failures expose Retry and a library
refresh link.

One inventory snapshot drives each static build, including all readers, inert
assets and byte-verified downloads. No document registration is needed: add or
remove an eligible deployed file and run the normal full/shared publication.
Refresh removes deleted readers/downloads from the candidate. Missing documents
return 404 with library navigation and Retry; a source file newly added after
publication becomes available in the next snapshot.

## Regenerate stale generated pages

Use the instance's actual canonical repository, read-model database and static
release root in place of the example variables below. These commands activate a
local release; run them only for the intended instance.

```sh
./v3 build-static --shared-only "$records" "$database" "$static_root"
```

The shared refresh includes the Docs index, complete library, search data and
attachment snapshots. It copies
the active complete release, renders into a candidate, then replaces the active
pointer only after success. If there is no active complete release, run:

```sh
./v3 build-static "$records" "$database" "$static_root"
```

A full build is also required for changed canonical records, retractions or a
static-detail-page mode transition; shared refresh cannot replace those steps.
All eligible Docs URLs use the shared eligibility policy and renderer. The
anonymous static host serves the active snapshot; personalized requests retain
the existing dynamic-response policy.

Reload `/docs/reference/author_post_retraction.md` after refresh. Verify four
tables, the uninterrupted six-step numbered procedure, emphasis and a working
`v3_cli.md#delete-a-canonical-record` link. Check at desktop and 390px widths,
Tab to table/code regions, and scroll horizontally with arrow keys. If a CDN or
browser still serves older output, invalidate the affected cached URL and retry;
do not delete the active release. A dynamic page and its generated counterpart
must have identical document content.

## Incomplete packages and rollback

A missing or unusable runtime produces HTTP 503 “Documentation unavailable”
with a retry instruction. The server error log retains the dependency/rendering
diagnosis. Docs index and unrelated routes do not load the parser. Generated
Docs failures abort a candidate build, preserving the previous active release;
an already-active generated page can remain readable while dynamic Docs fails.

Restore the previous **complete application package**, including its matching
renderer, templates/assets, manifest, lock, notices and vendor tree. Avoid mixing
versions or restoring one dependency file. Regenerate using the applicable command
above and reload the reported URL. Keep the previous complete package and active
release until acceptance passes. If regeneration fails, leave the active pointer
in place, inspect the CLI/server error and restore the complete package before
retrying. Existing atomic publication remains responsible for activation.

## Verification

```sh
php tests/run.php PlatformDocsPageTest PlatformDocsCatalogTest PlatformDocsStaticTest
node tests/browser/platform_docs.mjs
node tests/browser/docs_discovery.mjs
node tests/browser/docs_landing.mjs
node tests/browser/docs_landing_contrast.mjs [report.json]
python3 -I tests/theme_contrast_audit.py . report.json
python3 tests/platform_docs_release_test.py
```

The browser check defaults to `http://127.0.0.1:8007`; override
`DOCS_BASE_URL` and `DOCS_EVIDENCE_ROOT` as needed. It checks default, Word97,
Chicago and Midnight CSS at two widths without JavaScript. Preference scripts
are disabled, so theme CSS is applied directly by the harness.

The release check copies the complete application to a temporary directory,
runs the real full/shared build CLI against fixture records, serves dynamic and
generated documents, and demonstrates stale refresh, missing/partial runtime
503, failed-build retention and complete dependency-package restore plus
regeneration. No Composer process runs during these deployment checks.
It retains logs, screenshots and a JSON report in the printed temporary directory.
It never activates the source checkout's or a production instance's releases.

The discovery browser check uses `DOCS_DYNAMIC_BASE_URL` and
`DOCS_STATIC_BASE_URL` (defaults 8184/8185) plus `DOCS_EVIDENCE_ROOT`. Point these
at isolated fixture servers; the static server must have an active full release.
It checks 16 delivery/width/theme combinations, search/return/reset, inert assets,
HTTP download headers, missing/retry recovery and both Docs icon surfaces.
Static scenarios suppress preference-cookie writes to exercise the anonymous
fast path; theme CSS is
applied directly. Screenshots and machine-readable evidence are saved locally.

The landing check (`docs_landing.mjs`) compares browser-rendered results with
the JavaScript-off server render for representative queries, then runs the
known-user matrix: dynamic and static hosts (`DOCS_DYNAMIC_BASE_URL`,
`DOCS_CLIENT_BASE_URL`), JavaScript on/off, 1280px, 1280px at 200% zoom and
320px, in default, Word97, Chicago and Midnight. It asserts no page-wide
horizontal scrolling, readable diagram text, a visible focus ring while
tabbing, and the static no-JavaScript search notice. The contrast check measures
rendered text contrast for every docs component in every registered theme, with
the theme stylesheet kept in its real cascade position.
