# Forte Hidden Content Permalinks — Step 2: Feature Description

## Problem
Clicking a content link from the Forte Activity view leaves Forte for the classic interface whenever the underlying content is identity/bootstrap/approval-only, because that content is excluded from the Forte board's own thread listing and has no other way to be reached inside Forte.

## User Stories
- As a reader browsing Forte Activity, I want clicking a linked action's content to open a preview in place, so I don't lose my spot in Activity or get dropped into a different-looking interface.
- As a reader viewing a preview, I want a way to open the full thread in Forte's normal board view, so I can see the whole conversation.
- As a reader following any content link inside Forte, I want it to stay within Forte whether or not that content is normally listed on the board.

## Core Requirements
- Clicking a content link opens a scrollable modal summary in place, reusing the existing profile-summary dialog pattern (native `<dialog>`, API-fetched content, loading/error states) rather than a new mechanic.
- The modal includes a "View full thread" link that loads the thread into Forte's normal board panes.
- That link resolves correctly even for a thread excluded from the board's default listing (identity/bootstrap/approval-only), without adding the thread to the visible list, tag groups, or counts.
- Board-visible content keeps working exactly as today through both the modal and the full-thread link.
- No new URL scheme - the full-thread link keeps using the `selected=`/`created_post_id=` convention already used throughout Forte.

## Shared Component Inventory
- `showProfileSummary()` / `profileSummaryDialog` (`paned_board_reader.js` / `paned_profile_summary_dialog.php`) - the pattern this feature's new content-summary dialog follows, not forks.
- `/api/get_profile` - not reused directly (different data), but the precedent this feature's new small read endpoint follows.
- `renderForteBoard()` / `fetchThreads()` / `paned_board_content_pane.php` / `paned_board_thread_list.php` - extended so a directly-linked hidden thread's full view reuses the same content-pane rendering and reply-tree building every other thread already uses.
- `activityItemBoardLink()` - extended so every activity item (including currently-excluded ones) links into the new in-place preview instead of falling back to classic routes.
- `restoreSelectionFromUrl()` (`paned_board_reader.js`) - extended to select a thread that has no matching visible list row.

## Simple User Flow
1. Reader clicks a content link in Forte Activity (or elsewhere in Forte a similar link appears).
2. A modal opens in place with a scrollable summary of the linked post/thread - no navigation away.
3. Reader closes the modal and stays put, or clicks "View full thread."
4. "View full thread" loads the Forte board with that thread selected in the normal panes, even if the thread is excluded from the board's default listing.
5. For an already board-visible thread, the same modal and link behave identically to today.

## Success Criteria
- No content link in Forte Activity ever leaves Forte for the classic interface.
- Every content link opens a working preview modal, regardless of whether its thread is normally listed on the board.
- "View full thread" always lands on a correctly-selected thread in the Forte board, listed or not.
- A hidden thread reached this way never appears in the board's regular list, tag groups, or counts.
