# Forte Board View Step 2 Feature Description

## Problem
There is no board-level Forte entry point: Forte currently only reads one thread's replies. Users need a three-pane, Forte-Agent-styled view (folder tree of boards/tags, thread list, content preview) reachable from the Tools page, built entirely on existing thread/tag data (Option C from Step 1).

## User Stories
- As a forum reader, I want a folder tree of tags (with thread counts) so that I can narrow the thread list the way a newsreader narrows by newsgroup/folder.
- As a forum reader, I want a flat, sortable-feeling thread list so that I can scan subjects/authors/dates across the whole board or within a tag.
- As a forum reader, I want to preview a thread's root post and then jump into its full nested-reply Forte reader so that I can go from browsing to reading in two clicks.
- As a site operator, I want a link to Forte on the Tools page so that it's discoverable without knowing the URL.

## Core Requirements
- Folder tree (left pane): "All Threads" plus one entry per tag from the existing tag-grouping function, each showing the tag name and its thread count. Independently scrollable.
- Thread list (middle pane): flat, newest-first list of threads (subject, author, date, reply count) — no nesting, since threads aren't parent/child of each other the way replies are.
- Selecting a folder filters the thread list to that tag (or to everything, for "All Threads"), in place, client-side — no new page navigation (data for filtering is rendered upfront, so this step needs no new backend calls).
- Content pane (right pane): shows the selected thread's subject, author, date, and **full** body (not a truncated preview). If the thread has replies, a "Show N replies" toggle reveals a nested reply tree (indented by depth, author/date/body per reply) inline in the same pane — collapsed by default, no separate page. **Revises the original decision** to link out to the single-thread Forte reader instead; that link-through was removed after review — the board view is now a complete, self-contained reading experience.
- The whole Forte window (both this board view and the single-thread reader) fills the majority of the viewport in both width and height, with a slight margin, rather than being sized to its content — so long lists/bodies scroll inside their own pane instead of growing the page.
- New standalone route (`/forte`) with the same chrome treatment as the single-thread Forte page (title bar, menu bar, toolbar, status bar; no site nav bar or theme selector).
- Reachable from the Tools page via one new link.
- No schema/database changes.

## Shared Component Inventory
- `fetchThreads()` (`src/ForumRewrite/Application.php`) — reused unchanged as the thread-list data source.
- `groupThreadsByTag()` (`src/ForumRewrite/Application.php`) — reused unchanged as the folder tree's data source (same function that powers today's `/tags/` index).
- `fetchThreadPosts()` / `buildReplyTree()` (added for the single-thread Forte reader) — reused unchanged to build a selected thread's reply tree on demand.
- `TemplateRenderer::renderStandalonePage()` / `templates/standalone_layout.php` (added for single-thread Forte) — reused unchanged for this new route.
- Existing Forte chrome CSS (`.paned-window`, `.paned-titlebar`, `.paned-menubar`, `.paned-toolbar`, `.paned-statusbar`) — reused/extended for a three-pane, viewport-filling layout rather than forked.
- New surface (does not exist today): a folder-tree partial, a flat thread-list partial, a board-level content-pane partial (full body + reply-toggle, not a link-out), a reply-tree fragment partial, a new `/forte` route, and new client-side JS for folder/thread selection and lazy reply loading.
- Tools page (`renderTools()`) — extended with one new entry in its existing `toolPages` list; no changes to how that page renders.

## One deliberate exception: a small new fragment endpoint
At real scale (hundreds of threads, confirmed against a live instance during Stage 5 review) precomputing every thread's full reply tree upfront — the pattern used everywhere else in Forte to avoid new backend calls — would bloat the initial page substantially. Instead, a thread's reply tree is fetched lazily (one small HTML fragment per thread, only on first expand, cached client-side afterward) from a new route. This is the one place in the whole Forte feature that introduces a new backend endpoint, and it's scoped as narrowly as possible: read-only, no new tables/fields, reuses `fetchThreadPosts()`/`buildReplyTree()` unchanged.

## Simple User Flow
1. User opens the Tools page and clicks the new "Forte" link.
2. `/forte` loads, filling most of the viewport: folder tree shows "All Threads" (selected by default) plus each tag with its count; thread list shows every thread, newest first; content pane is empty until a thread is selected.
3. User clicks a tag in the folder tree; the thread list filters to that tag's threads only.
4. User clicks a thread in the list; the content pane shows its subject/author/date/full body. If it has replies, a "Show N replies" toggle appears, collapsed by default.
5. User clicks the toggle; the reply tree loads (lazily, once) and expands inline, indented by depth.

## Success Criteria
- Folder tree lists every tag `groupThreadsByTag()` returns, each with the correct thread count, plus "All Threads"; scrolls independently when it overflows its pane.
- Selecting a folder filters the thread list to exactly the threads carrying that tag (or all threads, for "All Threads").
- Selecting a thread shows its full body (not truncated) plus correct subject/author/date.
- A thread with replies shows a working expand/collapse toggle revealing an accurate nested reply tree; a thread with no replies shows no toggle.
- No new database fields, tables, or schema changes; exactly one new (read-only, fragment-returning) route, justified above.
- A working link to `/forte` appears on the Tools page.
- The Forte window (single-thread and board views) fills the majority of the viewport in both dimensions, with internal scrolling in each pane rather than page growth.
