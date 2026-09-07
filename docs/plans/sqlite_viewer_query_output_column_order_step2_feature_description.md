# SQLite Viewer Query Output Column Order Step 2 Feature Description

## Problem

The SQLite viewer’s preset query results expose useful data, but several examples lead with technical identifiers, timestamps, JSON fields, or internal metadata. Since the result renderer displays columns in SQL order, users must scroll past less meaningful fields before reaching the subject, author, label, or other content that explains the row.

The preset outputs should be easier to scan and more useful on narrow screens by putting human-readable and interesting fields first while preserving the existing query behavior and complete result data.

## User stories

- As a viewer user, I want the most readable fields—such as subjects, labels, usernames, and authors—at the start of each preset result so that I can understand rows quickly.
- As a viewer user, I want useful dates and activity metrics near the readable content so that I can interpret and compare rows without excessive horizontal scrolling.
- As a local SQLite user, I want the downloadable query pack to use the same column order as the browser viewer so that the examples remain consistent across environments.
- As a maintainer, I want column ordering defined in the canonical SQL sources so that generated assets do not require separate manual presentation mappings.

## Core requirements

- Reorder the `SELECT` lists for the existing SQLite viewer preset queries in `queries/sqlite/` according to a consistent readability priority.
- Put primary human-readable fields first, followed by relevant timestamps and metrics, then identifiers, JSON/blob-like fields, and internal or provenance fields.
- Preserve every currently selected field, including aliases, unless a separate approved scope change is made.
- Preserve each query’s joins, filters, grouping, ordering, limits, stable metadata, read-only behavior, and intended result meaning.
- Regenerate `public/assets/sqlite_viewer.js` and `public/assets/sqlite_query_catalog.sql` from the updated canonical sources.
- Keep ad hoc user-entered SQL results in their authored column order; this feature applies to the maintained example queries only.

## Shared component inventory

- **Canonical SQLite query sources:** `queries/sqlite/*.sql` define the preset SQL and are the only files to edit for the ordering change.
- **Catalog generator:** `scripts/build_sqlite_query_catalog.php` propagates source-query changes to browser and downloadable catalog outputs.
- **Browser query catalog:** the generated preset entries in `public/assets/sqlite_viewer.js` feed the existing selector and query input.
- **Result renderer:** `public/assets/sqlite_viewer.js` already renders columns in the order returned by SQLite, so no renderer change is required.
- **Downloadable query pack:** `public/assets/sqlite_query_catalog.sql` exposes the same reordered examples for local users.

## Simple user flow

1. A maintainer reviews each preset and identifies its readable fields, useful context, and technical fields.
2. The maintainer reorders only the relevant `SELECT` expressions in the canonical SQL source.
3. The catalog generator refreshes the browser catalog and downloadable query pack.
4. A viewer user loads the database, selects an example, and sees its readable columns first while scrolling through results.
5. A local user runs the downloaded example and sees the same column order.

## Success criteria

- Every maintained preset places its primary readable fields before technical identifiers and opaque metadata where those fields exist.
- Browser and downloadable catalog outputs reflect the canonical source order after regeneration.
- Preset queries return the same fields and rows, with unchanged filters, joins, ordering, limits, and aliases.
- Ad hoc SQL continues to display columns in the order authored by the user.
- All presets execute successfully against the current read-model database, and focused checks confirm the generated outputs are synchronized.

