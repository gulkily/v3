# Forte Board View Step 3 Development Plan

## Stage 1 - New /forte route and folder tree
- Goal: add the standalone `/forte` route with a stub page rendering the folder tree (All Threads + each tag with its thread count), proving routing and data reuse work before the other panes exist.
- Dependencies: none.
- Expected changes: new route `^/forte/?$` in `Application.php`; new `renderForteBoard(): string` calling existing `fetchThreads()` / `groupThreadsByTag()` unchanged; new partial `templates/partials/paned_folder_tree.php`; new page template `templates/pages/forte_board.php` rendered via the existing `renderStandalonePage()`.
- Verification: manual check — `GET /forte` returns 200; folder tree's tag names and counts match what `/tags/` already shows for the same fixture data.
- Risks/open questions: none expected; this is read-only reuse of an existing, already-tested function.
- Touches: `Application.php` (new route + method), new partial, new page template.

## Stage 2 - Flat thread-list pane
- Goal: render every thread as a flat, newest-first list (subject, author, date, reply count), each row carrying its tag membership as a data attribute for later client-side filtering.
- Dependencies: Stage 1 (page exists to render into).
- Expected changes: new partial `templates/partials/paned_board_thread_list.php` consuming `fetchThreads()`'s existing return shape; each row gets `data-paned-thread-id` and `data-paned-thread-tags` (from the thread's decoded `board_tags`).
- Verification: manual check — thread count and order match the existing board page (`/`) for the same fixture data.
- Risks/open questions: confirm the exact field name/shape for a thread's tag list coming out of `fetchThreads()` before wiring the data attribute.
- Touches: new partial only.

## Stage 3 - Board-level content pane
- Goal: render a preview block per thread (subject, author, date, body preview) plus a link into that thread's existing single-thread Forte reader; no reply rendering at this level.
- Dependencies: Stage 2 (same thread records available).
- Expected changes: new partial `templates/partials/paned_board_content_pane.php`; link href is `/threads/{root_post_id}/forte`.
- Verification: manual check — preview fields and the "Open in Forte" link's target are correct for a couple of sample threads.
- Risks/open questions: none expected; straight field reuse plus one computed href.
- Touches: new partial only.

## Stage 4 - Client-side folder/thread interaction
- Goal: clicking a folder filters the visible thread rows to that tag (or shows all for "All Threads"); clicking a thread row shows its preview in the content pane. No new backend calls.
- Dependencies: Stages 1-3 (all panes exist).
- Expected changes: new script `public/assets/paned_board_reader.js`, wired into `renderForteBoard()`'s script paths.
- Verification: headless-browser interaction check (same approach used for the single-thread Forte reader) — selecting a tag filters rows correctly; selecting a thread shows the correct preview and link.
- Risks/open questions: tag-string matching between the folder tree and each row's tag list must be normalized consistently (exact match, same casing) to avoid silent filter mismatches.
- Touches: new script only.

## Stage 5 - Chrome polish and Tools page link
- Goal: apply the existing Forte chrome (title bar with icon, menu bar, toolbar, status bar) to the three-pane layout, and add the Tools page link that motivated this feature.
- Dependencies: Stages 1-4 complete.
- Expected changes: finalize `templates/pages/forte_board.php` with full chrome; new CSS for the three-pane grid and folder-tree pane, extending (not forking) the existing `.paned-window`/`.paned-titlebar`/`.paned-menubar`/`.paned-toolbar`/`.paned-statusbar` rules; `renderTools()` gets one new entry in its existing `toolPages` array (`{label: 'Forte', href: '/forte', description: ...}`).
- Verification: screenshots at two viewport widths; confirm the Tools page renders the new link and it navigates to `/forte`.
- Risks/open questions: three-pane layout at narrow widths may need column stacking; resolved pragmatically (matching the single-thread Forte's existing narrow-width tolerance) rather than engineered to a specific breakpoint.
- Touches: `forte_board.php`, `site.css`, `renderTools()` in `Application.php`.
