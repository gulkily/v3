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

## Stage 3 - Full regression check
- Changes: none (verification only, as planned).
- Verification:
  - Tag-filter edge case: grabbed a reply permalink from a thread visible only under `?tag=bug`, confirmed the generated `href` carries no `tag=` param, then loaded it fresh *after* first visiting a different, unrelated tag (`?tag=testing`) — the target thread still selected correctly and the board defaulted to "All Threads," confirming a stale/mismatched filter can never hide the target.
  - Classic: `git diff` against `main` shows zero changes to `post.php`, `post_card.php`, or `thread_root_card.php`; `/posts/root-001` still renders and its own `post-card-permalink` anchor still points at itself, unchanged.
  - Zero console errors across the full regression run.
- Notes:
  - This completes all 3 planned stages for `forte_post_permalink`. Forte's board now has a working "#" permalink on every post and reply, reusing the board's existing on-load restore logic verbatim with no JS or backend changes — exactly the zero-new-code outcome Option A was chosen for.

## Post-completion fix - scroll the selected row into view
- Changes:
  - `paned_board_reader.js`: the initial-load restoration path now calls `initialSelectedRow.scrollIntoView({ block: "nearest" })` right after `selectThread()`, mirroring the scroll already done for the highlighted reply.
- Verification:
  - Reported by the user: following a permalink correctly selected the thread (confirmed via `.paned-list-row--selected`, verified earlier in automated testing) but the row was invisible in the threads list on a real screen — automated tests never caught this since they checked the CSS class, not scroll position.
  - Reproduced directly: selected a thread 400 rows down and measured its position against the list's scroll container — row was ~10,600px down against a ~340px-tall visible area, confirmed off-screen despite being correctly selected.
  - After the fix: same repro shows the row within the visible container bounds.
  - Re-ran Stage 2/3's permalink tests and the broader regression suite (New Thread dialog, Reply flow, multi-thread Like) — all still pass, zero console errors. The fix only touches the initial-load path, not `selectThread()` itself, so plain interactive clicks (where the clicked row is already visible) get no new scroll behavior.
- Notes:
  - This same gap existed for the reply-redirect landing case too, just unnoticed there since a reader who just replied is already scrolled near that thread. The fix benefits both paths.
