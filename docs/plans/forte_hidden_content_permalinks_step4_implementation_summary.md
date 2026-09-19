# Forte Hidden Content Permalinks — Step 4: Implementation Summary

## Stage 1 - Board-visibility-independent content-summary endpoint
- Changes:
  - New `GET /api/get_forte_content_summary?post_id=...` route, handled by new `handleForteContentSummary(array $query): void`.
  - New `forteContentSummary(string $postId): ?array` - resolves via the existing `fetchPost()` (the same lookup every other single-post read uses), not the board's own filtered `fetchThreads()`, so identity/bootstrap/approval-only posts resolve exactly like any other post. Returns `post_id`, `thread_id`, `is_reply`, `title` (via the existing `ThreadTitle::displayTitle()` helper), `author_label`, `created_at`, `body_preview` (full body - the dialog itself scrolls), and `reply_count` (from `threads.reply_count` for the post's root). `null`/404 for an unknown post id.
- Verification:
  - `php -l` - no syntax errors.
  - `GET ?post_id=<board-visible reply>` - 200, correct thread id, `is_reply: true`, `reply_count` matches the thread's real count.
  - `GET ?post_id=<hidden identity_bootstrap post>` (`agent-bootstrap-20260502071201-2d91e107`, excluded from the board's own listing) - 200, resolves exactly like a normal post - the entire point of this endpoint.
  - `GET ?post_id=<genuine root thread>` (`root-001`) - 200, `is_reply: false`, `thread_id` equals its own `post_id`.
  - `GET` with a nonexistent id, and with no `post_id` at all - both 404.
  - Regression: classic `/activity/`, `/forte/`, `/forte/activity/` all still 200.
- Notes:
  - Caught and fixed a wrong assumption during verification: `posts.thread_id` is self-referential for a root post (equals its own `post_id`), never `NULL` - so `thread_id !== null` is not a valid root/reply check for this table (unlike a similarly-named check on a different, write-side domain object seen elsewhere in this codebase, which does use `null` for "is a root"). `posts.parent_id` (`null` only for a root) is the correct discriminator here; caught by comparing actual query output for a known root post against a known reply before trusting the first draft.
  - `isApplicationRoute()`'s route allowlist (used only for members-only-mode gating) was not updated for this new endpoint - the same precedent as `/api/forte_commit_detail`/`/api/forte_activity_page`, neither of which are in that list either, and both already work correctly without it.

## Stage 2 - Content-summary modal on the Activity page
- Changes:
  - New `templates/partials/paned_content_summary_dialog.php`, mirroring `paned_profile_summary_dialog.php`'s structure exactly (native `<dialog>`, title bar with close button, loading/content/error states inside `.paned-standalone-body` - already scrollable via existing CSS, no new styles needed) with fields for kind (Thread/Reply), author, date, reply count, body text, and a "View full thread" link. Included from `forte_activity.php` as a sibling of `.paned-board-layout` (same placement `paned_profile_summary_dialog.php` uses in `forte_board.php`), so it survives `softNavigateToSort()`'s wholesale layout swap and only needs wiring once.
  - New `showContentSummary(postId, fullHref)` in `paned_activity_reader.js`, mirroring `showProfileSummary()` in `paned_board_reader.js` field-for-field: sets the "full link" href, opens the dialog, fetches Stage 1's endpoint, and populates the dialog (falling back to an error state on failure). A document-level click listener (bound once, matching the profile-link interception pattern including its modifier-key/middle-click bypass) intercepts `[data-forte-content-link]` clicks and opens the dialog instead of navigating - the anchor's real `href` stays set underneath for a no-JS/failed-JS fallback.
  - `paned_activity_detail_article.php`: content links now carry `data-forte-content-link data-post-id="<id>"` (`forte_link`'s `label` is already the target post id in every real content case) - excluded for `site_feature_flag`, whose link isn't a post at all.
- Verification:
  - `php -l` / `node --check` - no syntax errors.
  - Browser (Playwright): clicking a board-visible item's content link opens the dialog in place (URL unchanged), correct title/author/kind/reply-count/body populate from the live endpoint, close button closes it.
  - Browser: clicking a *hidden* `identity_bootstrap` item's content link (item 226, `view=bootstrap`) also opens the dialog correctly - this already works today, before Stage 3 even removes the classic-fallback href, because the interception only checks for `data-forte-content-link`/`data-post-id`, not the link's destination form. Its "View full thread" link still points at the classic route for now (expected - Stage 3 changes the href form, Stages 4-5 make that new form actually resolve).
  - Regression: normal row selection/keyboard nav on the Activity page unaffected; classic `/activity/` and the Forte board (`/forte/`) unaffected.
- Notes:
  - No `activityItemBoardLink()`/href changes in this stage - purely additive dialog/JS/template-attribute work, as scoped.
