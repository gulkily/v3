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
- Visual style follows Forte Agent's reference look (icon toolbar, menu bar, columned list header, gray subject/from/date bar above post content, status bar) at a moderate fidelity — layout and chrome, not a pixel-exact clone.
- Paned reader lives at its own page/route for a thread, fully isolated from the standard thread page: no link from the standard page to Forte, no link from Forte back to the standard page, and no site nav bar or theme selector on the Forte page — it presents its own complete, self-contained UI reachable only by its direct URL.
- Forte's window occupies the majority of the viewport on desktop, with a slight margin around it (not capped to the standard page's content-column width).
- No reply/compose affordances in Forte (no Reply or Permalink links) — this is a read-only reader.

## Shared Component Inventory
- `fetchThread()` / `fetchThreadPosts()` (`src/ForumRewrite/Application.php`) — reused unchanged; each post already carries `parent_id`, used to build the nested tree client-/server-side — no new backend calls.
- Author/date rendering helpers (`$author`, `$timestamp`) already used elsewhere in templates — reused for the content pane's subject/from/date bar rather than duplicating formatting logic.
- New surface (does not exist today, called out explicitly since Option B accepts a larger UI surface than Option A): a nested-list partial, a content-pane partial, a two-pane layout shell, a new page/route to host them, and a standalone page-rendering path with no site chrome (since Forte does not use the shared nav/theme layout).
- Read/unread state: still absent from the data model; still explicitly out of scope for this feature.

## Simple User Flow
1. User navigates directly to a thread's Forte URL (no link from the standard thread page).
2. Page renders as its own self-contained window: title bar, menu bar, icon toolbar, list pane with posts nested by `parent_id`, content pane showing the root post by default, status bar.
3. User clicks any list item, or uses the toolbar's Prev/Next buttons; content pane updates to that post's full content.
4. User reads through the thread; there is no reply/compose action inside Forte.
5. The standard thread page is entirely unaffected by Forte's existence — visiting one has no bearing on the other, and both can be open in separate tabs simultaneously.

## Success Criteria
- Every reply appears nested under its actual parent in the list pane, matching `parent_id` data exactly.
- Selecting any list item (via row click or toolbar Prev/Next) shows the correct corresponding post content with no page navigation.
- No new database fields, tables, or API endpoints introduced — only new rendering/layout surfaces and one new page route.
- Forte and the standard thread page are fully isolated: no cross-links in either direction, and the standard page's markup/behavior is completely unmodified by Forte's existence.
- Forte page has no site nav bar and no theme selector, and its window fills the majority of the desktop viewport with a slight margin.
- Visual treatment is recognizably Forte-Agent-like (icon toolbar + menu bar + columned list + gray content meta bar + status bar) per user review.
