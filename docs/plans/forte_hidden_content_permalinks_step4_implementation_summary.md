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

## Stage 3 - Every content link routes through the new preview
- Changes:
  - `activityItemBoardLink()`: dropped the `isBoardVisible` check and the classic `/posts/`/`/threads/` fallback branch entirely - any item with a resolvable thread/post id now always returns the `/forte?selected=<threadId>&created_post_id=<postId>#post-<postId>` form. `site_feature_flag`'s distinct destination is unchanged; an item missing a resolvable id still returns `['href' => '', 'label' => '']` (no link rendered at all, same as before).
- Verification:
  - `php -l` - no syntax errors.
  - Item 226 (hidden `identity_bootstrap`, `view=bootstrap`): link href is now the `/forte?selected=...` form (previously classic); clicking it opens the summary dialog correctly, and the dialog's "View full thread" link now also uses the new form (still won't resolve as a real board destination until Stages 4-5 - expected).
  - Item 1337 (`thread_label_add`, root `root-001`): link correctly resolves to the thread's root id; dialog opens with the root thread's own summary ("Hello world"), no error.
  - Regression: all 5 activity views (`all`/`content`/`identity`/`bootstrap`/`approval`) load correctly; classic `/posts/root-001`, classic `/activity/`, and RSS all still 200/unaffected (their own routes and rendering are untouched - only the Activity page's own link generation changed).
- Notes:
  - Between this stage and Stage 5, a hidden thread's "View full thread" link is a real, well-formed URL that doesn't yet land on working content - an expected, temporary gap called out in the Step 3 plan, closed by Stages 4-5.

## Stage 4 - Resolve a hidden thread on demand for the board
- Changes:
  - New `fetchThreadById(string $threadId): ?array` - the exact query `fetchThreads()` runs, minus its `isHiddenBootstrapBoardTagsJson` exclusion, for one thread by id; reuses the existing `hydrateThreadRow()` single-row hydrator so its shape matches every other thread row exactly.
  - `renderForteBoard()`: when `resolveForteBoardSelection()` can't find the requested selection in the board's own (filtered) `$threads`, it now tries `fetchThreadById()` directly; a hit sets `$selectedThreadId` and produces `$contentThreads` (`$threads` plus this one extra thread) - `$threads` itself, `$tagGroups`, and all counts stay untouched. Reply-tree building, `$allPostIds`, `$highlightedPostId`, and the viewer-like/flag lookups now iterate `$contentThreads` instead of `$threads`, so a hidden thread's replies/reactions render correctly too - `fetchAllThreadReplyPosts()` already covers every thread regardless of board-tag visibility (it only filters moderation's `is_hidden`), so no new reply-fetching was needed.
  - `forte_board.php` passes the new `$contentThreads` (not `$threads`) to `paned_board_content_pane.php` specifically - `paned_board_thread_list.php` and the folder tree/status bar keep receiving the original, unaffected `$threads`.
- Verification:
  - `php -l` - no syntax errors.
  - `GET /forte?selected=<hidden identity_bootstrap root>`: 200, its content article renders and is unhidden (selected), no matching row exists in the list, and `data-paned-board-total-count` is unchanged (530, same as a plain `/forte/` load).
  - `GET /forte?selected=<hidden thread with a real reply>&created_post_id=<that reply>`: 200, the reply tree renders inside the hidden thread's article (no PHP warnings/fatals in the response).
  - `GET /forte?selected=<nonexistent id>`: 200, falls back to the "no thread selected" placeholder exactly as before this stage (regression-safe for an unresolvable id).
  - Regression: `GET /forte?selected=<a normal, board-visible thread>` still gets both a list row and a content article, exactly as before.
  - Confirmed (expected, not yet fixed): visiting the dialog's "View full thread" link for a hidden thread in a real browser doesn't yet show it - the client-side `restoreSelectionFromUrl()` still requires a matching *visible row* before it will select anything, which this stage doesn't touch. This is precisely Stage 5's job.
- Notes:
  - `replyEnabled` (toolbar prop, `selectedThreadId !== ''`) becomes true for a hidden thread too once Stage 5 lets the client actually select it - left as-is deliberately: a reader replying to a bootstrap/hidden thread is an existing, valid capability this feature doesn't need to restrict, not a new one it's introducing.

## Stage 5 - Client-side selection for a thread with no list row
- Changes:
  - `restoreSelectionFromUrl()` in `paned_board_reader.js`: when `?selected=` matches no visible row, it now also checks for a matching content-pane article (`data-paned-board-content-post-id`) before giving up - only falling back to the "no thread selected" placeholder if neither exists. `selectThread()` itself needed no changes; its row-matching loop was already tolerant of zero matches. Row scroll-into-view is skipped when there's no row (nothing to scroll to); the reply-highlight/scroll behavior for `created_post_id` is unchanged and shared by both paths.
- Verification:
  - `node --check` - no syntax errors; unit-checked the new condition's operator precedence in isolation (no row + a selection -> falls through to the content-post check; a row present -> short-circuits to `null` immediately; no selection at all -> `null`) - matches intent in all three cases.
  - Browser (Playwright), direct URL: `/forte?selected=<hidden thread>&created_post_id=<reply>#post-<reply>` - the hidden thread's content article is visible, the placeholder is hidden, no console errors.
  - Browser, full end-to-end: Activity item (hidden `identity_bootstrap`, item 226) -> content-summary dialog -> "View full thread" -> lands on the Forte board with that exact thread now visible and selected. No console errors anywhere in the flow.
  - Regression: a normal, board-visible thread (`root-001`) still gets its list row highlighted and scrolled into view on direct selection; a nonexistent id and no-selection-at-all both still show the placeholder exactly as before; clicking an ordinary row still selects it.
- Notes:
  - This closes the gap Stage 4 explicitly left open - the feature's end-to-end flow (Activity link -> in-place preview -> full thread, for board-visible and hidden content alike) is now fully working. Stage 6 is the final regression/integration pass across the whole feature.

## Stage 6 - End-to-end regression pass
- Changes: none - verification-only stage, as scoped.
- Verification (all via a real browser, Playwright):
  - All 5 non-commit activity views (`content`/`identity`/`bootstrap`/`approval`, plus `all` covered throughout earlier stages) - one representative item per view, clicking its content link opens the dialog with correct data, and "View full thread" lands on a correctly-selected, visible thread in the board. Covered both a root-post item and a reply item (approval view item 234: a reply into thread `bootstrap-20260915111820-50f698ae`) - the reply case confirms the thread's root is selected *and* the specific reply is highlighted via `created_post_id`, not just "some content is visible."
  - Commits view (Stage 3-era work, untouched by this feature): 100 commit rows still render, clicking one still opens its on-demand manifest correctly - confirms this feature's changes to the shared activity-item article template didn't regress the separate commit-row/detail code path.
  - Forte board's own profile-summary dialog (the pattern this feature's dialog was modeled on) still opens correctly from an author link inside a selected thread - confirms the new dialog didn't interfere with the existing one (distinct `data-role`/dialog-selector attributes throughout).
  - Classic `/posts/<id>`, `/threads/<id>`, `/activity/`, and RSS (`/activity/?format=rss`) - all still 200, all untouched by this feature (only Forte's own link-generation and board-selection logic changed).
  - Zero browser console errors across every scenario above.
- Notes:
  - No issues surfaced requiring code changes - the feature works end-to-end as specified in Step 2, across board-visible and hidden content, roots and replies, with no regressions to the Commits view, the profile-summary dialog, or the classic interface.

## Post-completion fixes (after "Approved Step 4" was requested)
- **Close on backdrop click; message-box styling.** User feedback: the content-summary dialog should close when clicking outside it, and should read as a plain message box (gray header, white body, "View full thread" in a gray footer) rather than the app's own blue-titlebar window chrome.
  - `paned_content_summary_dialog.php`: dropped the shared `.paned-new-thread-dialog` class (used by the app's other dialogs, which should keep their current look); restructured into three explicit zones - `.paned-content-summary-header`, `.paned-content-summary-body`, `.paned-content-summary-footer` (the "View full thread" link moved out of the content area into this new persistent footer, so it's available even during loading).
  - `forte.css`: new dedicated rules for the three zones (gray header/footer via `--paned-chrome-dark`, white body via `--paned-content-bg`) scoped entirely to `.paned-content-summary-dialog` - the profile-summary and new-thread dialogs are untouched.
  - `paned_activity_reader.js`: added a click listener on the dialog element itself, closing it when `event.target === dialog` (the standard way to detect a native `<dialog>`'s backdrop click, since the dialog's own box is sized to its content).
- Verification:
  - Browser (Playwright): header/body/footer computed background colors match the design (gray/white/gray); clicking inside the body leaves the dialog open; clicking the backdrop closes it; the existing close button still works.
  - Regression: the board's profile-summary dialog still opens with its original blue titlebar, unaffected.

- **Follow-up correction: message-box styling replaced with a closer match to the real interface, plus a real page-load bug fix.** User clarified with a reference screenshot (a classic Windows dialog: blue titlebar, plain gray body, an inset white preview box with margin on all sides, buttons in a plain gray footer) and specific corrections: metadata should live in the header area formatted like the main interface's own post header, the titlebar should be blue (not gray, undoing the prior fix's header-recoloring), only the post's own text should be white (not the whole body area), and that white area needs visible margin around it. They also caught a real bug: an empty, uninitialized dialog was appearing on every page load.
  - **Bug fix**: `.paned-content-summary-dialog` had an unconditional `display: flex` - since author-origin CSS always wins over the browser's own UA stylesheet regardless of selector specificity, this silently overrode the browser's default `dialog:not([open]) { display: none }`, so the closed dialog rendered inline on every page load instead of staying hidden. Fixed by moving `display: flex` onto `.paned-content-summary-dialog[open]` instead, so it only applies once the dialog is actually shown.
  - **Structure**: rebuilt the dialog's body as a miniature, literal clone of the main reader's own content pane - reusing `.paned-content-pane` (white, `margin: 0.5rem`, bordered - this *is* where the "margin all around" comes from) containing an `<article class="paned-content-post">` with `.paned-content-head`/`.paned-content-subject`/`.paned-content-meta` (gray, holding the post's title and Kind/From/Date/Replies) and `.post-card .body` (the actual post text, on white) - the exact same classes and DOM shape `paned_activity_detail_article.php` uses, not a lookalike. The dialog's own titlebar reverted to the shared `.paned-dialog-titlebar` (blue, matching every other dialog in the app) with a fixed "Post Preview" label; the footer (`.paned-content-summary-footer`, plain background, top border, "View full thread") sits below, unchanged in spirit from the prior fix.
  - `paned_activity_reader.js`: `showContentSummary()` updated for the new field locations (subject/kind/author/date/replies now populate the head/meta area instead of a single hidden/shown content block); the backdrop-click-to-close listener from the prior fix needed no changes.
- Verification:
  - Browser (Playwright): confirmed `getComputedStyle(dialog).display === "none"` on a fresh page load (the bug is gone); opened the dialog and confirmed each zone's actual background color (blue titlebar, gray content-head, white `.paned-content-pane`, `margin: 0.5rem` on the pane) plus a full-page screenshot showing the result reads as a coherent message box closely matching the reference image's layout.
  - Regression: hidden-content item (226) still shows correct data and its "View full thread" link still resolves to the right board thread; an unknown post id still shows the error state gracefully; the board's own profile-summary dialog is unaffected.
