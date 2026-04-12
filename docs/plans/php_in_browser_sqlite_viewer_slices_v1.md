# PHP In-Browser SQLite Viewer Slices V1

This document outlines the implementation slices for adding an in-site SQLite database viewer and query runner to the PHP forum rewrite.

## Goal

Let a technical user open a page within the website, load the published SQLite index database in the browser, and inspect/query it there.

The first retained version should:

- live inside the existing website as a normal route
- load the already-published `/downloads/read_model.sqlite3` file client-side
- avoid adding any server-side arbitrary SQL execution surface
- make the read-model tables discoverable and useful to technical users
- stay read-only

## Product Direction

This should feel like a built-in technical inspection surface, not a separate tool and not a production admin console.

That means the retained approach should prefer:

- a normal page route such as `/instance/sqlite/`
- browser-side database loading using a client-side SQLite runtime such as `sql.js`
- read-only interaction against the downloaded SQLite file
- progressive disclosure: explorer first, ad hoc query runner second

And should avoid:

- sending user-authored SQL to the server
- mutating the database
- pretending this is a general-purpose database admin interface
- coupling the feature to privileged access unless a real product reason appears

## Current Context

The repo already has:

- a published database download at `/downloads/read_model.sqlite3`
- an `Instance` page that already exposes technical download links
- a template/rendering system for adding normal site routes
- lightweight browser-side JavaScript patterns for page-specific behavior

So the main work is:

- adding a new browse page
- loading SQLite in-browser
- defining a safe and usable read-only query/explorer UX

## Core Design Choice

The query engine should run entirely in the browser against the downloaded SQLite file.

Why:

- the database is already intentionally published for technical users
- this avoids creating a server-side SQL endpoint
- it keeps the feature aligned with the forum's portability and inspectability goals
- it reduces security and abuse concerns compared with remote query execution

In practice this means:

- the page fetches `/downloads/read_model.sqlite3`
- JavaScript loads the file into an in-memory SQLite runtime
- all browsing and query execution happen locally in the page session

## Slice 1: Route And Viewer Shell

Focus:

- create an in-site destination and page shell for the feature

Checklist:

- choose a retained route such as `/instance/sqlite/`
- add the route in `src/ForumRewrite/Application.php`
- add a template such as `templates/pages/sqlite_viewer.php`
- link to the page from `Instance` or another technical surface
- explain clearly on the page that queries run locally in the browser against the published SQLite file
- provide a visible `Load Database` action and a loading/error state

Expected outcome:

- a technical user can discover the feature from the site and reach a dedicated viewer page

## Slice 2: Client-Side SQLite Load

Focus:

- make the browser successfully load and open the published database

Checklist:

- add a browser-side SQLite dependency such as `sql.js`
- load `/downloads/read_model.sqlite3` from the page
- instantiate an in-memory database from the downloaded bytes
- render a simple success state with file/source context
- render failure states for fetch failure, corrupt DB, or runtime load failure
- ensure the page still works when the database is temporarily unavailable

Expected outcome:

- the page can open the real published SQLite index database fully client-side

## Slice 3: Schema Explorer

Focus:

- make the loaded database inspectable even before arbitrary SQL is introduced

Checklist:

- list available tables from SQLite metadata
- show a selected table's columns
- allow opening a small preview of rows from a selected table
- include the expected forum tables such as `posts`, `threads`, `profiles`, `activity`, and `metadata`
- add row limits so the first version stays responsive
- surface empty/error states cleanly

Expected outcome:

- a technical user can browse the schema and inspect real rows without writing SQL

## Slice 4: Read-Only Query Runner

Focus:

- add ad hoc SQL inspection for technical users

Checklist:

- add a SQL textarea/editor on the same page
- execute queries only against the in-memory browser-loaded DB
- enforce read-only behavior in the UI contract
- render result tables for `SELECT` queries
- show clear error messages for invalid SQL
- add a sensible default row cap or result truncation behavior
- include a few canned example queries for common inspection tasks

Expected outcome:

- a technical user can run exploratory read-only queries from within the site

## Slice 5: Usability And Technical Polish

Focus:

- make the feature pleasant enough to retain

Checklist:

- add example queries for key tables and joins
- add copy/export options for query results if worthwhile
- add schema/help text that explains what the main tables represent
- show the database source URL and maybe rebuild metadata from the `metadata` table
- keep mobile behavior acceptable even if the main UX is desktop-first
- ensure the page works under the existing theme system without becoming visually noisy

Expected outcome:

- the feature is understandable and useful rather than merely technically possible

## Recommended First-Version Category Of User

This is primarily for:

- technical users
- operators
- curious power users

It is not necessary to optimize the first version for casual users who do not know SQL.

That said, the schema explorer slice is still important because it gives non-SQL technical users an entry point.

## Open Decisions To Lock Early

- exact route: `/instance/sqlite/`, `/instance/database/`, or similar
- whether the page should auto-load the DB or require an explicit button click
- which SQLite runtime/package to vendor
- whether to allow only single-statement queries in V1
- how aggressive row/result limits should be
- whether the page belongs in the main nav or only under `Instance`

Recommended defaults:

- route under `Instance`
- explicit `Load Database` action
- client-side read-only single-statement query flow
- modest default row limits
- discoverable from `Instance`, not top-level nav

## Risks And Constraints

- browser-side SQLite/WASM assets add weight to the page
- large result sets can freeze or degrade the UI if uncapped
- arbitrary SQL text still needs UX guardrails even when run locally
- mobile UX for query tables will be inherently limited

None of these argue against the feature, but they do argue for slice discipline.

## Recommended Order

1. add the route and page shell
2. make the browser load the published SQLite DB
3. add schema/table exploration
4. add the read-only SQL runner
5. add examples and polish

## Summary

This feature should be built as an in-site technical page that runs entirely in the user's browser against the already-published SQLite database.

The key product decision for V1 is:

- keep execution client-side and read-only
- make the feature accessible from within the website
- deliver it in slices so loading, exploration, and ad hoc querying stay separately reviewable
