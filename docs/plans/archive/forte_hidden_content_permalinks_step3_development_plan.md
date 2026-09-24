# Forte Hidden Content Permalinks — Step 3: Development Plan

## Stage 1
- Goal: A lightweight, board-visibility-independent read endpoint returning one post/thread's summary by post id.
- Dependencies: none.
- Expected changes: new `GET /api/get_forte_content_summary?post_id=...` route; new `Application` method (e.g. `renderApiForteContentSummary(string $postId): ?array`) returning subject/title, author label, created date, body preview, thread id, reply count, and whether it's a reply - resolved directly against `posts`, not the board's filtered thread query; `null`/404 for an unknown id.
- Verification approach: curl the endpoint for a board-visible post, a hidden identity/bootstrap post, and a nonexistent id - confirm correct JSON shape and 404 respectively.
- Risks or open questions:
  - Keep the field set minimal - only what a scrollable summary needs, not a full thread payload.
- Canonical components/API contracts touched: new endpoint, following the existing `/api/get_profile` precedent's shape (small, purpose-built read).

## Stage 2
- Goal: A scrollable content-summary modal on the Activity page, reusing the profile-summary dialog pattern.
- Dependencies: Stage 1.
- Expected changes: new partial (e.g. `paned_content_summary_dialog.php`) mirroring `paned_profile_summary_dialog.php`'s structure (title, loading/error states, body, a "full thread" link); included from `forte_activity.php`; new `showContentSummary(postId, fullHref)` in `paned_activity_reader.js` mirroring `showProfileSummary()` - fetches Stage 1's endpoint, populates the dialog, opens it via `showModal()`; click-interception on content links opens the dialog instead of navigating, while the link's real `href` stays set (progressive-enhancement fallback if JS fails).
- Verification approach: clicking a content link opens the dialog with correct summary data and no navigation; dialog closes via its close control.
- Risks or open questions:
  - None - self-contained new markup/JS, no shared template modified yet.
- Canonical components/API contracts touched: `showProfileSummary()`/`profileSummaryDialog` pattern (new sibling dialog, not a modification).

## Stage 3
- Goal: Every activity item's content link goes through the new preview instead of falling back to the classic interface.
- Dependencies: Stage 2.
- Expected changes: `activityItemBoardLink()` always returns the `/forte?selected=<threadId>&created_post_id=<postId>#post-<postId>` form for any item with a resolvable thread/post id, regardless of board visibility - the classic `/posts/`/`/threads/` fallback branch is removed. `site_feature_flag`'s distinct (non-content) destination is unchanged.
- Verification approach: a previously-hidden item's content link now opens the dialog (not a classic route); the dialog's "View full thread" href matches the new form (it won't fully resolve until Stage 4/5 land - expected, temporary gap).
- Risks or open questions:
  - `thread_label_add` items use the thread's root id as their "post id" (no distinct post) - confirm the Stage 1 summary endpoint handles a root-post id correctly.
- Canonical components/API contracts touched: `activityItemBoardLink()`.

## Stage 4
- Goal: `/forte?selected=<id>` resolves and renders a thread excluded from the board's default listing, without adding it to the visible list, tag groups, or counts.
- Dependencies: none (parallel to Stages 1-3; this is what their "full thread" link needs by the time Stage 5 wires client selection).
- Expected changes: new helper (e.g. `fetchThreadById(string $threadId): ?array`), an unfiltered single-thread fetch reusing `fetchThreads()`'s row/hydration shape; `renderForteBoard()` extended so that, when the requested selection isn't in the normally-filtered thread set but the thread genuinely exists, it's fetched and passed to the content pane and reply-tree building only - kept out of the list-rendering data, tag groups, and counts.
- Verification approach: `?selected=<hiddenThreadRootId>` renders that thread's content article in the response; the same response's row list/tag counts are unaffected (no new row, no count change).
- Risks or open questions:
  - `paned_board_content_pane.php` and `paned_board_thread_list.php` currently share one `$threads` prop - needs a second, pane-specific value without changing either template's existing contract for normal threads.
- Canonical components/API contracts touched: `renderForteBoard()`, `paned_board_content_pane.php`, `paned_board_thread_list.php` (extended, not forked).

## Stage 5
- Goal: The board's client-side selection logic shows a hidden thread's content pane even though it has no matching list row.
- Dependencies: Stage 4.
- Expected changes: `restoreSelectionFromUrl()` in `paned_board_reader.js` - when `selected` matches no visible row, fall back to checking for a matching content-pane article and select it directly (skipping row highlighting/scroll, since none exists).
- Verification approach: browser check - `/forte?selected=<hiddenThreadRootId>&created_post_id=<postId>#post-<postId>` shows the right thread with the right reply highlighted, no console errors, no ghost row in the list.
- Risks or open questions:
  - Reply/compose affordances assume a normally-listed thread context - confirm they degrade sensibly (e.g. stay enabled/disabled consistently) rather than erroring; not expanding reply behavior itself, just not breaking it.
- Canonical components/API contracts touched: `restoreSelectionFromUrl()`, `selectThread()` (extended).

## Stage 6
- Goal: Confirm the full flow end-to-end and catch any integration gaps before completion.
- Dependencies: Stages 1-5.
- Expected changes: none anticipated beyond fixes surfaced by testing.
- Verification approach: full regression pass - each of Activity's 6 views' content links open the dialog; board-visible content's "View full thread" and hidden content's "View full thread" both resolve correctly; classic `/posts/`/`/threads/` and RSS unaffected; no console/server errors.
- Risks or open questions:
  - None anticipated - integration/verification stage only.
- Canonical components/API contracts touched: none new.
