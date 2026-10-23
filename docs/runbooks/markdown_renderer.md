# Documentation library, rendering and recovery

Existing Platform Docs links and direct `/docs/...md` URLs use the project-owned
`PublicMarkdownRenderer` for both PHP responses and generated HTML. PHP 8.1+
and mbstring are required. Deploy the complete application. Documentation rendering
has no third-party parser bundle, Composer installation or dependency-download step.

## Rendering boundary

The project parser renders headings, paragraphs, basic asterisk emphasis,
inline code/links, backtick fences, quotes, thematic breaks and supported lists.
Integration retains duplicate-safe heading IDs, catalog link safety, collapsed
escaped front matter, code language captions and keyboard-accessible code regions.

Lists support space-indented `-`, `+`, `*`, numbered-dot and numbered-parenthesis
items, mixed parent/child types and up to eight levels. Root markers start at
column zero; child markers must reach their parent's content column, while
siblings/dedents return to established columns. Wrapped and blank-line-separated
paragraphs aligned with the owning content column stay inside the item. Each
ordered number is explicit, preserving restarts, repeats and gaps; marker-kind
changes start sibling lists. Five-to-eight-level lists get a keyboard-scrollable
region so phone text retains usable width.

Tabs, ambiguous indentation, over-limit depth, more than four marker-padding
spaces, numeric markers above nine digits and list-contained tables, quotes or
fenced/indented code retain the complete contiguous list as escaped source in a
keyboard-scrollable region. Fenced example bytes remain intact; prose comments
remain hidden. Following standalone headings, tables and code stay outside the
fallback. An unclosed embedded fence retains the remaining example source through
end-of-document rather than guessing where it ends.

Supported unordered items beginning with `[ ]`, `[x]` or `[X]` followed by spaces
and visible task text display native disabled checkboxes. Their first paragraph
supplies the accessible task name; children and later paragraphs remain separate.
Repeated tasks require no generated identifiers. Checkboxes cannot be toggled or
submitted; task links and keyboard access to links/source regions still work.
Original Markdown and downloads are unchanged. Ordered items, malformed/escaped
markers, empty/invisible labels, later-paragraph markers, code/table examples and
unsupported whole-list source regions keep literal task text. No progress counter
or editing feature is included.

Reference links, autolinks, underscore emphasis, strikethrough and unsupported
fences remain readable source. Standalone pipe tables render with semantic column
headers, existing safe inline formatting/links, optional outer pipes, two or more columns and delimiter
alignment (`:---`, `:---:`, `---:`). Header and body widths must match; empty cells,
escaped pipes and pipes inside single-backtick inline code are supported. Tables
scroll horizontally in a keyboard-focusable region without widening the page.

Malformed/uneven tables, uncertain or multi-backtick code spans, and nested/list
or quote table forms retain readable source. Two-or-more-space-indented pipe
blocks use the source fallback; multiline/merged cells and nested tables are not
implemented. A recognized table block falls back as a whole, keeping every cell;
ordinary prose and fenced examples remain separate. Unrecognized pipe prose is
not a table. Cell links retain the same unavailable-link alternative and document
boundaries as prose; images still never load. Mermaid remains labeled source;
there is no diagram, math, slideshow or syntax-highlighting engine.

Raw HTML is escaped, prose comments are hidden, and standalone image markup has an
escaped alt-text alternative. Images never load. Inline links pass the catalog's
HTTP(S), mail, fragment and documentation-boundary policy; unsafe, unsupported,
traversal and symlink destinations are unavailable. General Markdown compatibility
and source ambiguities remain explicit limitations. See the [frozen baseline and
counting method](../plans/markdown_dependency_removal/render_metrics.md), [restored
comparison](../plans/markdown_dependency_removal/render_comparison.md), and
[rare-syntax decision table](../plans/markdown_dependency_removal/rare_syntax_decisions.md).
The [pipe-table extension](../plans/markdown_renderer_extension/markdown_renderer_extension_step4_implementation_summary.md)
records its supported/fallback corpus classification and delivery evidence. The
[list-structure slice](../plans/markdown_list_structure/markdown_list_structure_step4_implementation_summary.md)
adds list ownership, explicit numbering and source recovery; its audit distinguishes
structural coverage from representative visual review. Other syntax extensions
and the wider Docs column follow-up remain deferred. The
[task-list slice](../plans/markdown_task_lists/markdown_task_lists_step4_implementation_summary.md)
records disabled state/name/link checks and the displayed-versus-source corpus
classification. The requested in-page checkbox counter is a separate todo item. No external component may be added without
the dependency-consent policy in CONTRIBUTING.

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

Use the instance's canonical repository, static root and `FORUM_SITE_ID` profile:

```sh
./v3 docs publish --repository-root="$records" --static-html-root="$static_root"
```

This independently publishes the complete Docs index, library, browse/search data,
source downloads and assets to the static root's `-docs` sibling. Use `--json` for
automation. An unchanged compatible library can be reused; changed or damaged
inputs/output cause full rendering only in this explicit command. The current docs
pointer changes after complete validation; failure retains the previous library.
Correct the cause and retry with the same options/profile.

Imports, resumes, full forum builds and shared refreshes never regenerate or bulk
scan/copy Docs. They retain the last published library, including legacy combined
releases until independent publication succeeds. Forum content changes still use
normal rebuild/publication procedures. Docs publication does not change the forum
release, read model or forum offline snapshot.

Before first publication, PHP readers remain available. Published docs serve the
same public anonymous snapshot with or without cookies and on private profiles.
Static-only hosting must separately publish and map the docs library/downloads and
its retained fingerprinted/scoped assets; forum output alone no longer includes
Docs. Serve canonical reader URLs through the documented PHP routes when PHP is
available; do not expose arbitrary application or canonical-record source files.

Reload `/docs/reference/author_post_retraction.md` after refresh. Verify four
rendered, scrollable tables, the six source-numbered procedure steps, emphasis
and the working `v3_cli.md#delete-a-canonical-record` link. Check at desktop and
390px widths, Tab to table/source/code regions and scroll with arrow keys. If a CDN or
browser serves older output, invalidate the affected URL and retry; do not delete
the active release. Dynamic and generated document content must match.

## Incomplete applications and rollback

A controlled rendering failure returns HTTP 503 “Documentation unavailable” with
a retry instruction. Logs retain the diagnosis; the index and unrelated routes
remain available. A failed generated Docs build aborts its candidate and preserves
the previous active release. Restore the previous complete **dependency-free**
application package, regenerate with the command above and retry the reported URL.
Do not restore the removed third-party parser or mix application versions. Keep the
active release until acceptance passes; historical cleanup must account for any
older contaminated release/archive rather than retaining it indefinitely as rollback.

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

The release check copies the application without a third-party Markdown runtime,
runs the real full/shared CLI against fixtures and serves dynamic/generated Docs.
It demonstrates stale refresh, injected rendering failure/503, failed-build retention
and dependency-free package restoration/regeneration. No Composer process runs.
Logs, screenshots and a JSON report remain in the printed temporary directory;
source/production releases are never activated by the test.

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
