# Forte Post Permalink — Step 2: Feature Description

## Problem
Forte has no shareable direct link to one specific post — only a thread-level link (`/forte?selected={threadId}`), with no way to point at one specific post or reply within it.

## User Stories
- As a Forte board-view reader, I want a "#" permalink on each post/reply, so I can share a link straight to it instead of "the third reply in this thread."
- As someone who opens a shared Forte permalink, I want to land on the board with the right thread selected and the target post highlighted and scrolled into view, so I don't have to hunt for it myself.
- As a reader with a tag filter active when I generate or follow a permalink, I want it to still work, so the link isn't silently broken by whatever filter state I happened to be in.

## Core Requirements
- Every post (thread root and replies) gets a small "#" permalink anchor, mirroring classic's own `post-card-permalink` affordance, styled for the paned chrome.
- The link points to `/forte?selected={threadId}&created_post_id={postId}#post-{postId}` — reusing `paned_board_reader.js`'s existing on-load restore logic verbatim (per approved Step 1, Option A): select the thread, find the post, highlight it, scroll to it.
- Permalink URLs never include a tag filter (`tag=`) — the target thread's row must always be visible on load regardless of whatever filter the reader had active, since `selectThread()` skips hidden rows.
- No new JS, no new backend route, no database changes — this is markup-only, generating URLs the board already knows how to restore.
- No changes to classic's own `/posts/{id}` page or its `post-card-permalink` link.

## Shared Component Inventory
- `paned_board_reader.js`'s `initialSelected`/`initialCreatedPostId` on-load restore logic — **reused unchanged**; originally built for the post-reply redirect landing, already generic enough for any post ID.
- Classic's `post-card-permalink` anchor (`post_card.php`) — **not reused directly** (points at classic's `/posts/{id}`, a different URL entirely); Forte gets its own equivalent anchor with the same "#" affordance convention, pointed at the board URL instead.
- `paned_board_content_pane.php` (thread root) / `paned_thread_reply_tree.php` (replies) — **extended** with the new anchor, alongside the Like/Flag buttons `forte_reactions` already added there.

## Simple User Flow
1. Reader clicks the "#" next to any post or reply.
2. The browser's address bar now shows a link they can copy and share.
3. Anyone who opens that link lands on `/forte` with the right thread selected, the target post highlighted, and scrolled into view — the same restore experience a fresh reply already gets today.

## Success Criteria
- Every post and reply on the board has a working "#" permalink.
- Following a permalink selects the correct thread and highlights/scrolls to the correct post, with zero JS changes required to do it.
- A permalink still works correctly even when generated or followed while a tag filter was active.
- Classic's `/posts/{id}` and its own permalink link are completely unaffected.
