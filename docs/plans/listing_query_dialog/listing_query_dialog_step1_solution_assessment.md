> **Feature plan:** [Step 1](./listing_query_dialog_step1_solution_assessment.md) · [Step 2](./listing_query_dialog_step2_feature_description.md) · [Step 3](./listing_query_dialog_step3_development_plan.md) · [Step 4](./listing_query_dialog_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Let's work on this idea from todo.txt, please:

Every page that has data listing should have a query to reproduce that listing in the database viewer with the locally saved data and immediately run the query and make it easy to edit and modify the query.
The database interface should open in a popup dialog that is as instant as possible and retain state and preserve background page state, easy to return to.
We should update existing viewable queries, such as in /tools/codebase/, to this new mode.

## Problem

Users need to inspect and modify the query behind a listing without losing their place or repeatedly loading the database.

## Option A — Shared viewer inside a retained dialog (recommended)

Reuse the existing database viewer in one dialog per page, retaining the loaded database, edited SQL, results, and viewer position when closed. Listing actions supply the corresponding query and run it automatically once the local data is ready.

- Pros: preserves background state; fast reopening; one consistent editor and result interface; existing query displays can use the same entry point.
- Cons: first use still requires runtime/data readiness; state across full page navigation needs explicit persistence; opening a different listing must protect existing edits.

## Option B — Retained embedded viewer page

Open the existing viewer page inside a persistent dialog frame and pass listing queries to it, keeping the frame alive between openings.

- Pros: isolates the viewer layout; reuses its standalone page; preserves background state and warm reopening.
- Cons: adds communication and focus complexity; duplicate page navigation and styling; local-data coordination remains necessary.

## Option C — Separate viewer window

Send listing queries to a reusable browser window that retains its database and editor while the user navigates the site.

- Pros: can retain a warm viewer across page navigation; easy side-by-side inspection.
- Cons: popup restrictions and window management; does not provide the requested in-page dialog experience.

## Recommendation and scope checks

Choose Option A, retaining standalone viewer access. Closing and reopening resumes the current work; invoking another listing deliberately opens its matching query, with recovery for unsaved edits. Show the dialog shell immediately, reuse ready data, and communicate first-load progress and retry failures.

The current viewer fetches `/downloads/read_model.sqlite3`; offline reading also uses a distinct saved snapshot. Define local-data selection, freshness, and persistence explicitly in Step 2. Queries should match active filters, ordering, and the intended pagination scope against the selected saved data; an older snapshot cannot promise identical live rows.

Inventory all listing pages and existing SQL displays before fixing delivery scope. Some listings use separate stores or non-database sources: do not silently omit them or expose private data through the public database. Identify coverage gaps and obtain agreement on any phased rollout. No schema changes are assumed.

The first releasable slice must include opening from a real listing and `/tools/codebase/`, automatic execution, editing/rerunning, close/reopen retention, background-page recovery, and load-error retry. Extend that shared path across the agreed listing inventory; split delivery only if the full inventory exceeds Railspike's one-day/eight-stage limit.

## Evidence

- `public/assets/sqlite_viewer.js`: explicit database load, local SQL execution, editable queries, sorting, and pagination already exist.
- `templates/pages/codebase_state.php`: existing SQL source displays coexist with shell/file sources.
- `templates/pages/offline_reader.php`: offline snapshot source differs from the viewer's published read model.
