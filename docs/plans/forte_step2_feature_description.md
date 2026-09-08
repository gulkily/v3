# Forte Step 2 Feature Description

## Problem
Users want a two-pane, Forte-Agent-styled thread reader — a nested-reply title list in one pane and a content pane for the selected post in the other — built from existing thread/post data, with no new storage or protocol work (Option B from Step 1).

## User Stories
- As a forum reader, I want a list pane showing thread replies nested by who-replied-to-whom so that I can see the reply structure at a glance, like a classic newsreader.
- As a forum reader, I want to click a title in the list pane and see its full content in an adjacent pane so that I can read through a thread without page reloads.
- As a forum reader, I want the paned reader styled after Forte Agent (toolbar, columned list header, split list/content layout) so that it feels like a classic newsreader rather than the current card layout.

## Core Requirements
- Build the nested reply tree from each post's existing `parent_id` — no schema change, no new queries beyond the current fetch functions.
- List pane shows post titles/subjects indented by reply depth (root post at top, replies nested under their actual parent).
- Content pane displays the selected post's full body, author, and date, reusing the same fields already rendered today.
- Selecting a list item updates the content pane in place, without a full page reload.
- Visual style follows Forte Agent's reference look (toolbar, columned list header, distinct list/content split) at a moderate fidelity — layout and chrome, not a pixel-exact clone.
- Paned reader lives at its own page/route for a thread, separate from the standard thread page; the standard thread page is unchanged and remains fully accessible — no in-place mode toggle.

## Shared Component Inventory
- `fetchThread()` / `fetchThreadPosts()` (`src/ForumRewrite/Application.php`) — reused unchanged; each post already carries `parent_id`, used to build the nested tree client-/server-side — no new backend calls.
- `templates/partials/thread_root_card.php` / `post_card.php` — existing per-post fields (author, date, body, reactions, agent-reply markers) are the source of truth for the content pane; **extended** into a new content-pane partial rather than duplicating field definitions.
- Standard thread page — **extended** with a simple link/button to open the new paned-reader route for the same thread; no toggle mechanism needed since this is a separate page, not an in-place view swap.
- New surface (does not exist today, called out explicitly since Option B accepts a larger UI surface than Option A): a nested-list partial (title + depth indent per post), a two-pane layout shell, and a new page/route to host them.
- Read/unread state: still absent from the data model; still explicitly out of scope for this feature.

## Simple User Flow
1. User opens an existing thread page.
2. User opens the paned reader via a link on that thread page; it loads as a separate page for the same thread.
3. Page renders two panes: list pane with posts nested by `parent_id`, content pane showing the root post by default.
4. User clicks any list item; content pane updates to that post's full content.
5. User replies or reacts using existing controls, now surfaced within the content pane.
6. User returns to the standard thread page via plain navigation (link/back) at any time; it is unaffected by having visited the paned reader.

## Success Criteria
- Every reply appears nested under its actual parent in the list pane, matching `parent_id` data exactly.
- Selecting any list item shows the correct corresponding post content with no page navigation.
- No new database fields, tables, or API endpoints introduced — only new rendering/layout surfaces and one new page route.
- Paned reader is reachable via a link from the standard thread page; the standard thread page remains fully accessible and unmodified.
- Visual treatment is recognizably Forte-Agent-like (toolbar + columned list + split pane) per user review.
