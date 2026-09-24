# SQLite Download With Query Web UI Step 3 Development Plan

- Route decision: use `/tools/sqlite/`, linked from the existing technical tools/backup navigation.
- Out of scope: on-demand full-text search, FTS index/schema changes, persistent database caching, server-side SQL execution, and database writes.

## Stage 1
- Goal: Add the discoverable viewer route and shared site page shell.
- Dependencies: Approved Step 1 and Step 2; existing tools navigation and template renderer.
- Expected changes: Route GET `/tools/sqlite/`; add `Application::renderSqliteViewer(): string`; add the page template, source/read-only explanation, load control, and empty/loading/error placeholders.
- Verification approach: Route smoke test confirms status, title, viewer copy, and shared navigation; unsupported methods retain the existing response.
- Risks or open questions:
  - Confirm the final viewer link placement in the tools/backup navigation.
- Canonical components/API contracts touched: `Application::handle()` routing; `Application::renderPageTemplate()`; shared layout/navigation; new viewer page template.

## Stage 2
- Goal: Load the published SQLite artifact entirely in the browser.
- Dependencies: Stage 1; selected browser SQLite runtime and its browser/WASM assets.
- Expected changes: Add the page-specific runtime assets; fetch `/downloads/read_model.sqlite3`; open it in the browser; expose source and load status; handle fetch, runtime, and invalid-database failures.
- Verification approach: Browser/manual checks load the real artifact and each failure state; existing download test confirms its response remains unchanged.
- Risks or open questions:
  - Runtime asset size and browser compatibility may require a vendor choice or loading adjustment.
- Canonical components/API contracts touched: `/downloads/read_model.sqlite3`; page-specific asset loading; browser-only database session.

## Stage 3
- Goal: Provide schema and bounded table exploration without requiring SQL.
- Dependencies: Stage 2 loaded database and viewer state.
- Expected changes: Render table names, selected-table columns, bounded row previews, and empty/error states; keep previews responsive and clearly identify the loaded source.
- Verification approach: Use the published database to inspect representative `posts`, `threads`, `profiles`, `activity`, and `metadata` tables; verify row limits and empty states.
- Risks or open questions:
  - Published schemas may evolve, so the explorer must not depend on only one fixed table set.
- Canonical components/API contracts touched: SQLite metadata/table inspection; existing read-model terminology and browse-page links.

## Stage 4
- Goal: Add the preset-query selector and optional read-only query runner.
- Dependencies: Stage 2; Stage 3 table context.
- Expected changes: Add one viewer-owned query catalog containing labels, descriptions, and query text; render selector options; execute selected or user-entered read-only queries locally; reject or report non-read-only/multi-statement input according to the V1 contract.
- Verification approach: Preset queries cover obvious forum inspections; valid ad hoc reads return data; write attempts, invalid input, and unsupported statements never reach the server.
- Risks or open questions:
  - Exact read-only validation and single-statement behavior need to be confirmed against the selected runtime.
- Canonical components/API contracts touched: New preset-query catalog; browser query execution surface; no new server API.

## Stage 5
- Goal: Make query results safe and understandable.
- Dependencies: Stage 4 query execution.
- Expected changes: Render bounded tabular results, column headings, truncation/limit feedback, query errors, no-results states, and links where existing identifiers map to forum pages.
- Verification approach: Exercise empty, small, large, malformed, and failing queries; verify the page remains usable on narrow screens and does not render unescaped database values as markup.
- Risks or open questions:
  - Large cells or result sets may still affect browser responsiveness and need a conservative cap.
- Canonical components/API contracts touched: Shared table/card styling; existing post/thread/profile/activity URL conventions; browser result renderer.

## Stage 6
- Goal: Integrate and verify the retained feature as a coherent technical tool.
- Dependencies: Stages 1–5.
- Expected changes: Add final tool navigation copy and source/download links; document the local read-only behavior; add focused route/rendering and asset smoke coverage; remove unused placeholders.
- Verification approach: Run the relevant PHP test suite, route smoke checks, and a manual browser pass covering load, browse, preset selection, ad hoc read, errors, and direct download.
- Risks or open questions:
  - FTS and persistent client-side caching remain future work and must not enter implementation by implication.
- Canonical components/API contracts touched: Existing tools navigation; Backup/Instance download surface; `/downloads/read_model.sqlite3`; page-specific viewer assets and tests.
