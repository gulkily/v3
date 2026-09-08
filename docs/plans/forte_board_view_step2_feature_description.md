# Forte Board View Step 2 Feature Description

## Problem
There is no board-level Forte entry point: Forte currently only reads one thread's replies. Users need a three-pane, Forte-Agent-styled view (folder tree of boards/tags, thread list, content preview) reachable from the Tools page, built entirely on existing thread/tag data (Option C from Step 1).

## User Stories
- As a forum reader, I want a folder tree of tags (with thread counts) so that I can narrow the thread list the way a newsreader narrows by newsgroup/folder.
- As a forum reader, I want a flat, sortable-feeling thread list so that I can scan subjects/authors/dates across the whole board or within a tag.
- As a forum reader, I want to preview a thread's root post and then jump into its full nested-reply Forte reader so that I can go from browsing to reading in two clicks.
- As a site operator, I want a link to Forte on the Tools page so that it's discoverable without knowing the URL.

## Core Requirements
- Folder tree (left pane): "All Threads" plus one entry per tag from the existing tag-grouping function, each showing the tag name and its thread count.
- Thread list (middle pane): flat, newest-first list of threads (subject, author, date, reply count) — no nesting, since threads aren't parent/child of each other the way replies are.
- Selecting a folder filters the thread list to that tag (or to everything, for "All Threads"), in place, client-side — no new page navigation, no new backend calls (mirrors how the single-thread Forte reader already avoids new API calls).
- Content pane (right pane): shows the selected thread's subject, author, date, and body preview, plus a link into that thread's existing single-thread Forte reader (`/threads/{id}/forte`) to read replies — this pane does not itself render nested replies.
- New standalone route (`/forte`) with the same chrome treatment as the single-thread Forte page (title bar, menu bar, toolbar, status bar; no site nav bar or theme selector; majority-of-viewport width).
- Reachable from the Tools page via one new link.
- No schema/database changes; no new API endpoints.

## Shared Component Inventory
- `fetchThreads()` (`src/ForumRewrite/Application.php`) — reused unchanged as the thread-list data source.
- `groupThreadsByTag()` (`src/ForumRewrite/Application.php`) — reused unchanged as the folder tree's data source (same function that powers today's `/tags/` index).
- `TemplateRenderer::renderStandalonePage()` / `templates/standalone_layout.php` (added for single-thread Forte) — reused unchanged for this new route.
- Existing Forte chrome CSS (`.paned-window`, `.paned-titlebar`, `.paned-menubar`, `.paned-toolbar`, `.paned-statusbar`) — reused/extended for a three-pane layout rather than forked.
- New surface (does not exist today): a folder-tree partial, a flat thread-list partial (distinct from the single-thread reply-tree list, since threads have no parent/child relationship to each other), a board-level content-pane partial (preview + link out, not a reply reader), a new `/forte` route, and new client-side JS for folder/thread selection.
- Tools page (`renderTools()`) — extended with one new entry in its existing `toolPages` list; no changes to how that page renders.

## Simple User Flow
1. User opens the Tools page and clicks the new "Forte" link.
2. `/forte` loads: folder tree shows "All Threads" (selected by default) plus each tag with its count; thread list shows every thread, newest first; content pane is empty until a thread is selected.
3. User clicks a tag in the folder tree; the thread list filters to that tag's threads only.
4. User clicks a thread in the list; the content pane shows its subject/author/date/preview and an "Open in Forte" link.
5. User clicks that link to read the thread's nested replies via the existing single-thread Forte page.

## Success Criteria
- Folder tree lists every tag `groupThreadsByTag()` returns, each with the correct thread count, plus "All Threads".
- Selecting a folder filters the thread list to exactly the threads carrying that tag (or all threads, for "All Threads").
- Selecting a thread shows the correct subject/author/date/preview, and its "Open in Forte" link points to the correct thread's `/threads/{id}/forte` URL.
- No new database fields, tables, or API endpoints — only new rendering surfaces and one new route.
- A working link to `/forte` appears on the Tools page.
- Visual treatment matches the existing Forte chrome, extended to three panes.
