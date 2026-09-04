# SQLite Download With Query Web UI Step 2 Feature Description

## Problem

The site publishes a SQLite read-model database, but users who cannot or do not want to open the file locally have no in-site way to inspect it. Add a discoverable, read-only viewer with schema browsing and useful preset queries, while keeping the existing downloadable artifact available.

## User stories

- As a technical user, I want to inspect the published SQLite database in my browser so that I can understand the forum’s indexed data without installing local tools.
- As an operator, I want to browse tables and run common read-only queries so that I can investigate the current read model quickly.
- As a curious user, I want preset queries and plain-language guidance so that I can start exploring without already knowing the schema.
- As a maintainer, I want the viewer to use the same published database and existing site conventions so that its results remain consistent with downloadable data and normal browse pages.

## Core requirements

- Provide a stable in-site page, discoverable from the existing technical/backup tools area, that explains the database source and its read-only scope.
- Allow the user to load the published SQLite database in the browser and clearly show loading, success, unavailable, and invalid-database states.
- After loading, expose the available tables, columns, and a bounded row preview so users can explore without writing SQL.
- Provide a read-only query surface with a selector of clearly labeled preset queries plus optional ad hoc SQL, bounded results, and clear invalid-query/error states; no user-authored SQL is sent to the server.
- Preserve the existing `/downloads/read_model.sqlite3` download contract and avoid changing unrelated browse, activity, or backup behavior.

## Shared component inventory

- `Application::renderBackup()` and `templates/pages/instance.php` (`/instance/`, `/backup/`, `/tools/backup/`): extend the existing technical tools navigation and add the viewer entry; retain the current download and freshness presentation.
- `/downloads/read_model.sqlite3` via `Application::handleReadModelDatabaseDownload()`: reuse unchanged as the viewer’s published database source and continue exposing it as a direct download.
- Read-model-backed board/thread, profile, and activity pages: treat their existing projections and links as the canonical interpretation of indexed forum data; do not create alternate content renderers for the viewer.
- `Application::renderCodebaseState()` and its System State page: reuse only as contextual read-model health information if needed; do not duplicate its status model inside the viewer.
- Shared page layout, navigation, and page-specific asset conventions: reuse the canonical site shell and navigation rather than introducing a standalone tool interface.
- Preset-query catalog: introduce one viewer-owned collection of query definitions used by the selector, labels, explanations, and execution path so additional obvious queries can be added without duplicating page markup.

## Simple user flow

1. The user opens the viewer from the technical tools/backup area.
2. The page identifies the published SQLite source and offers a load action.
3. The user loads the database and sees its available tables and bounded previews.
4. The user selects a preset or enters a read-only query and reviews the bounded results or an understandable error.
5. The user follows existing forum links or downloads the SQLite file for deeper local analysis.

## Success criteria

- A user can reach the viewer from the existing technical tools area and load the published database in a supported browser session.
- Successful loading exposes table names, column information, and at least one bounded preview for a non-empty table.
- Preset queries return understandable results, while invalid and over-limit queries produce visible feedback without a page failure.
- The query selector makes the initial obvious queries discoverable and leaves a clear extension point for later searches, including full-text search.
- Browser inspection causes no server-side SQL execution and leaves the existing SQLite download response unchanged.
- The viewer’s linked records and terminology remain consistent with the corresponding existing board, profile, activity, backup, and system-state surfaces.
