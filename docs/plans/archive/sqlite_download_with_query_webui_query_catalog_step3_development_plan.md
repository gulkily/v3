# SQLite Viewer Query Catalog Step 3 Development Plan

- Scope boundary: externalized read-only sample queries, generated browser catalog, and a companion local-use query pack.
- Out of scope: on-demand FTS, FTS indexes/schema changes, persistent browser database caching, server-side SQL execution, database writes, and embedded SQLite views.
- Database impact: none; use the existing read-model tables and download contract.

## Stage 1
- Goal: Define the query-file format and catalog validation contract.
- Dependencies: Approved Steps 1–2; existing inline presets and query candidate list.
- Expected changes: Establish per-file metadata comments, stable identifiers, categories, ordering, duplicate checks, and read-only/single-statement validation; define generator entry point such as `buildQueryCatalog(sourceDir, browserOutput, localOutput)`.
- Verification approach: Fixture files cover valid metadata, malformed metadata, duplicate identifiers, invalid SQL, and disallowed statements.
- Risks or open questions:
  - Confirm the SQL parser/runtime available for build-time validation.
- Canonical components/API contracts touched: Query-file format; existing SQLite read-only execution contract; no database schema.

## Stage 2
- Goal: Move the current viewer presets into canonical `.sql` sources.
- Dependencies: Stage 1 format and validation contract.
- Expected changes: Add one `.sql` file per existing preset, preserve the default liked+newest query exactly, and remove duplicated inline query text from the browser source.
- Verification approach: Compare generated entries with the current labels, descriptions, ordering, and SQL; execute all existing presets against a representative read model.
- Risks or open questions:
  - Preserve formatting and stable identifiers so bookmarks or future references remain predictable.
- Canonical components/API contracts touched: `public/assets/sqlite_viewer.js` query selector; current board/activity/profile query behavior.

## Stage 3
- Goal: Generate the browser query catalog from the SQL sources.
- Dependencies: Stages 1–2.
- Expected changes: Add the repository build integration and generated catalog payload consumed by the existing query runner; ensure one-shot browser delivery and deterministic output.
- Verification approach: Clean generation produces every source entry once; browser smoke coverage confirms selector labels, descriptions, default selection, and execution.
- Risks or open questions:
  - Determine whether generated output belongs in source control or is rebuilt as part of deployment.
- Canonical components/API contracts touched: Existing SQLite viewer selector/input/result renderer; page asset loading; build/static-artifact workflow.

## Stage 4
- Goal: Provide the same catalog for local use with the SQLite download.
- Dependencies: Stage 3 generated catalog and existing download route.
- Expected changes: Generate a companion query-pack artifact with metadata comments and readable SQL; expose it through the existing downloads/tool surface while leaving the SQLite database unchanged.
- Verification approach: Download and inspect the pack, confirm it contains the same entries and SQL as the browser catalog, and verify the existing database download response is unchanged.
- Risks or open questions:
  - Choose and document the companion filename/content type and whether the pack is plain SQL or an archive.
- Canonical components/API contracts touched: `/downloads/read_model.sqlite3`; download navigation; generated local-use query artifact.

## Stage 5
- Goal: Add prioritized interface and activity/statistics presets.
- Dependencies: Stages 1–4; approved query candidate list; current board and activity behavior.
- Expected changes: Add bounded parity queries for board/activity views and selected aggregate diagnostics, with concise metadata and stable categories; defer exploratory and FTS candidates.
- Verification approach: Execute representative entries against the current read model and compare parity entries with their corresponding interface outputs, including filters, joins, selected fields, and ordering.
- Risks or open questions:
  - Keep result sizes bounded and avoid assuming optional diagnostic tables exist in every downloaded database.
- Canonical components/API contracts touched: Board/thread routes; activity route/view filters; existing read-model tables and URL terminology.

## Stage 6
- Goal: Complete automated and maintainer-facing verification.
- Dependencies: Stages 1–5.
- Expected changes: Add focused generator/parser tests, catalog consistency checks, browser asset smoke coverage, download artifact checks, and concise contributor documentation for adding `.sql` entries.
- Verification approach: Run focused tests, full relevant PHP/JavaScript checks, clean generation, direct artifact inspection, and a manual browser pass covering load, default query, selection, editing, errors, and local downloads.
- Risks or open questions:
  - Generated artifacts can drift if validation is not part of the normal build/test path.
- Canonical components/API contracts touched: Build/test commands; SQLite viewer; download surface; contributor documentation.
