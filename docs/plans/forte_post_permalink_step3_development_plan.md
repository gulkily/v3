# Forte Post Permalink — Step 3: Development Plan

## Stage 1
- Goal: Add a working permalink anchor to each thread's root post.
- Dependencies: none (Step 2 approved)
- Expected changes: `paned_board_content_pane.php`'s `.post-card.paned-post-card` gains a small "#" anchor (alongside the Like/Flag buttons `forte_reactions` already added there) linking to `/forte?selected={threadId}&created_post_id={threadId}#post-{threadId}` (no `tag=`); `forte.css` gains a small rule matching classic's `post-card-permalink` affordance, styled for the paned chrome.
- Verification approach: click the anchor on a root post, confirm the resulting URL matches the expected shape; open that URL fresh (new tab/reload) and confirm the correct thread is selected — root-post highlighting isn't expected to visually differ (no `data-paned-reply-post-id` on the root card), so verify via thread selection only for this stage.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_content_pane.php` (extended), `forte.css` (extended).

## Stage 2
- Goal: Add the same permalink anchor to every reply node.
- Dependencies: Stage 1 (styling already in place)
- Expected changes: `paned_thread_reply_tree.php`'s per-node HTML builder gains the same "#" anchor, linking to `/forte?selected={threadId}&created_post_id={replyPostId}#post-{replyPostId}`.
- Verification approach: click a reply's anchor, confirm the URL shape; open that URL fresh and confirm the thread is selected *and* the specific reply is highlighted/scrolled into view (the real end-to-end case Option A exists for).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_thread_reply_tree.php` (extended).

## Stage 3
- Goal: Full regression check, including the tag-filter edge case.
- Dependencies: Stages 1-2
- Expected changes: none (verification only)
- Verification approach: follow a reply permalink while a tag filter is active in the browser (simulating a reader clicking a shared link cold, with no prior board state) and confirm the target thread/post still restores correctly, not silently hidden by a stale filter; confirm classic's `/posts/{id}` and its own permalink link are byte-for-byte unaffected; confirm no console errors.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-2.
