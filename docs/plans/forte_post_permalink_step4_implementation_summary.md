# Forte Post Permalink — Step 4: Implementation Summary

## Stage 1 - Root-post permalink
- Changes:
  - `paned_board_content_pane.php`: the root post's `.paned-reaction-row` gains a "#" anchor linking to `/forte?selected={threadId}&created_post_id={threadId}#post-{threadId}` (no `tag=`).
  - `forte.css`: new `.paned-permalink-link` rule matching the paned chrome (muted color, underline on hover).
- Verification:
  - `php -l` clean.
  - Headless-browser test (Selenium + `chromium-browser`, cache-busted URL): clicked into a thread, read the rendered anchor's `href`, then opened it as a **fresh navigation** (simulating a reader clicking a shared link cold, not a same-session DOM check) — the correct thread was selected on load with zero console errors.
- Notes:
  - As anticipated in Step 3: the root post has no `data-paned-reply-post-id`, so `created_post_id` selects the thread but doesn't trigger the highlight/scroll code path (which only matches reply nodes). Expected and harmless — the root post is already the first thing visible once its thread is selected, so there's nothing meaningful to scroll to.

## Stage 2 - Reply permalink
- Changes:
  - `paned_thread_reply_tree.php`: each reply node's `.paned-reaction-row` gains the same "#" anchor, using the post's own `thread_id` (already present on every row from `fetchAllThreadReplyPosts()`) to build `/forte?selected={threadId}&created_post_id={replyPostId}#post-{replyPostId}`.
- Verification:
  - `php -l` clean.
  - Headless-browser test, fresh navigation: found a thread with a visible reply, read its permalink `href`, opened it cold — the correct thread was selected *and* the specific reply carried `.paned-highlight-new` (the real end-to-end case Option A exists for), zero console errors.
- Notes: none identified.
