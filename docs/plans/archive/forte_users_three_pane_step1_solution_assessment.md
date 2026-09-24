# Forte Users Three-Pane Layout — Step 1: Solution Assessment

## Problem
The Forte Users page (`/forte/users/`) is a single flat list, while Board and Activity already offer a filter pane + listing pane + detail pane, and we want Users to match that pattern.

## Option A: Mirror the existing per-section pattern (new partials + new JS controller)
- Add `paned_users_filter_list.php`, `paned_user_list.php`, `paned_user_detail_pane.php` partials and a `paned_users_reader.js` controller, following the exact structure Board/Activity already use (URL-param selection state, flex `paned-panes-stack` layout, reuse of the shared `paned_toolbar.php`).
- Detail pane loads via the existing `/api/get_profile` fetch endpoint (already used by Board's profile dialog) instead of pre-rendering every user's full detail server-side.
- Pros: consistent with two existing precedents; isolated to new files with zero risk to Board/Activity; fits comfortably within a day/8-stage Step 3 budget; reuses an existing API endpoint rather than forking a new payload shape.
- Cons: continues the existing duplication of pane/URL-state plumbing across a third near-identical JS controller instead of consolidating it.

## Option B: Extract a shared 3-pane layout + JS module first, then build Users on it
- Factor the shared flex layout markup and URL-state/selection JS logic (currently duplicated between `paned_board_reader.js` and `paned_activity_reader.js`) into one shared partial + JS module, refactor Board and Activity onto it, then implement Users on top.
- Pros: removes duplication going forward; a single pane/URL-state implementation to maintain.
- Cons: touches working, in-production Board/Activity code for a story that only asked for Users; refactor + regression risk pushes this well past a one-day/8-stage budget; scope creep relative to the request.

## Recommendation
**Option A.** It matches the pattern the user asked for, stays isolated to new files (no regression risk to Board/Activity), and reuses the existing `/api/get_profile` endpoint for the detail pane instead of inventing a new payload. Option B's consolidation is worth pursuing later as its own refactor story, not bundled into this one.
