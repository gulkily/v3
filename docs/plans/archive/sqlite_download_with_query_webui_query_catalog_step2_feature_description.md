# SQLite Viewer Query Catalog Step 2 Feature Description

## Problem

SQLite viewer sample queries are currently maintained in browser JavaScript, which makes them harder for developers to inspect, review, and extend. The project needs a maintainable query catalog that serves both the web selector and advanced users working with downloaded database files.

## User stories

- As a developer, I want each sample query in an individual repository `.sql` file so that I can inspect, review, and modify it easily.
- As an advanced user, I want the same sample queries available with a downloaded SQLite database so that I can reproduce useful views locally.
- As a web user, I want clearly labeled query presets so that I can explore common interface views and activity statistics without writing SQL first.
- As a maintainer, I want query metadata and validation to come from one source so that the browser catalog and local-use artifacts stay consistent.

## Core requirements

- Each catalog entry has readable SQL plus stable metadata for label, description, category, and ordering.
- The web viewer exposes the catalog through its existing query selector and preserves the current default query and read-only execution behavior.
- The catalog includes parity queries for prioritized board/activity views and bounded summary queries for useful statistics.
- The SQLite download workflow makes the catalog available for local use without requiring a read-model schema change.
- Full-text search, persistent browser storage, and embedding the catalog as SQLite views are out of scope for this cycle.

## Shared component inventory

- **Board/thread views:** existing canonical board and thread query behavior; catalog entries reproduce these views and must reuse their filters, joins, selected fields, and ordering rather than define divergent behavior.
- **Activity views:** existing activity route and view-specific query behavior; activity presets reuse the canonical view distinctions and ordering.
- **SQLite viewer query runner:** existing selector, editable SQL input, and result renderer; extend this surface to consume the generated catalog rather than creating a second query UI.
- **SQLite database download:** existing read-model download endpoint; extend the download workflow with the query catalog artifact or companion file, without changing the database schema.
- **Existing inline sample presets:** current viewer catalog source; replace it with generated catalog data so there is one authoritative source.

## Simple user flow

1. A maintainer adds or updates a catalog `.sql` file and its metadata.
2. Project validation confirms metadata, SQL, naming, and catalog ordering are valid.
3. The build produces the browser catalog and the local-use query artifact.
4. A web user loads the SQLite database, chooses a labeled preset, and runs or edits it.
5. An advanced user downloads the database and query artifact, then runs the same queries locally.

## Success criteria

- Every browser preset is traceable to exactly one repository `.sql` file.
- The default board preset produces the same selected fields, constraints, joins, and ordering as the current interface view.
- A clean build produces both browser-consumable catalog data and a downloadable local-use artifact.
- At least one board parity query, one activity-view query, and one activity/statistics query execute successfully against the current read model.
- Adding a catalog entry requires changing its `.sql` source and regenerated outputs, with no duplicated query text maintained by hand.
