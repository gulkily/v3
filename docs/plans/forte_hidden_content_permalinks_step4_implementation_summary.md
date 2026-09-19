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
