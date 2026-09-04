# SQLite Viewer Query Catalog Step 1 Solution Assessment

## Problem Statement

The viewer’s sample queries should be maintainable as individual repository `.sql` files, compiled into the web client, expanded to cover useful interface and activity views, and made available to advanced users of downloaded database artifacts.

## Option A: SQL files with comment metadata and generated browser/query-pack outputs

Pros:
- Keeps each query’s readable SQL, label, description, category, and ordering together.
- Provides one canonical source for the browser selector and a local downloadable query pack.
- Supports adding the additional interface-view and activity-stat queries incrementally.
- Leaves the existing read-model schema and SQLite download contract unchanged.

Cons:
- Requires a small metadata parser and build/validation step.
- Generated browser assets must be refreshed when query files change.
- Query-pack versioning and compatibility with database schema versions need clear conventions.

## Option B: SQL files plus a separate metadata manifest

Pros:
- Metadata has an explicit structured format with straightforward validation.
- Query files remain pure SQL and can be consumed directly by other tools.
- Easier to support richer metadata later.

Cons:
- Query identity and presentation metadata can drift from the SQL files.
- Adds a second catalog file to maintain and review.
- Less convenient for a developer browsing one query at a time.

## Option C: Store sample queries as SQLite views or catalog rows

Pros:
- A downloaded database can carry reusable query definitions or projections with it.
- Local SQLite users can discover the samples from inside the database.
- No separate query-pack file is required for embedded definitions.

Cons:
- Makes viewer samples part of the read-model schema and rebuild process.
- Views are a poor fit for parameterized or presentation-specific queries.
- Schema changes increase compatibility and migration obligations.
- Catalog rows add metadata to a database intended primarily as a read model.

## Recommendation

Recommend Option A, with a generated downloadable query pack alongside the SQLite download and a deliberately prioritized catalog of additional interface and activity-stat queries.

Brief justification:
- It gives developers canonical, reviewable `.sql` files and gives web users a single generated catalog without duplicating query text.
- It serves local users without changing the database schema, while leaving embedded views as a later, separately justified enhancement.
