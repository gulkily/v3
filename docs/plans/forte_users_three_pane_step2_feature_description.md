# Forte Users Three-Pane Layout — Step 2: Feature Description

## Problem
The Forte Users page is a flat list of names, unlike Board and Activity's filter + listing + detail layout, so admins can't filter or inspect a user without leaving the page.

## User Stories
- As an admin, I want to filter the Users list (e.g., by role/status) so that I can quickly find the users I'm looking for.
- As a viewer, I want to select a user from the list and see their details in a side pane so that I don't have to navigate to a separate page.
- As an admin, I want the Users page to look and behave consistently with Board and Activity so that navigation feels familiar across the app.

## Core Requirements
- Filter pane (left) listing filter categories, mirroring the Board/Activity filter-pane pattern.
- Listing pane showing the filtered set of users as selectable rows, with selection state kept in the URL like Board/Activity.
- Detail pane showing the selected user's profile in place, without a full-page navigation.
- Shared toolbar/layout chrome (`paned_toolbar.php`, `paned-window`/`paned-board-layout` CSS) reused unchanged, with "Users" as the active tab.
- No database schema changes; the existing approved user directory data source is reused as-is.

## Shared Component Inventory
- **Toolbar** — `paned_toolbar.php`: existing, reused unchanged (`activeView: 'users'`).
- **Layout chrome** — `paned-window` / `paned-board-layout` / `paned-panes-stack` CSS classes in `forte.css`: existing, reused; no new layout system introduced.
- **Detail data source** — `/api/forte_user_detail` (new, added in Step 4 Stage 1): a fragment endpoint mirroring the existing `/api/forte_commit_detail` pattern (`renderFragment` + `{status, html}`), not `/api/get_profile` — that endpoint is keyed by a single `profile_slug` and returns plain text, which doesn't fit the `username_token`-aggregated directory.
- **Filter/listing partials** — no existing partial fits; `paned_folder_tree.php` (tags) and `paned_activity_filter_list.php` (fixed activity views) are shaped for their own domains. New partials are needed: `paned_users_filter_list.php`, `paned_user_list.php`, `paned_user_detail_pane.php`, following the same markup/URL-state conventions as Board/Activity.
- **JS controller** — no existing controller covers Users; new `paned_users_reader.js` needed, mirroring the URL-state pattern in `paned_board_reader.js` / `paned_activity_reader.js`.

## Simple User Flow
1. User navigates to `/forte/users/`.
2. Page loads with filter pane, listing pane (approved user directory), and "Users" active in the shared toolbar.
3. User applies a filter, narrowing the listing pane.
4. User clicks a row; selection updates the URL and highlights the row.
5. Detail pane fetches and displays the selected user's profile via `/api/get_profile`.

## Success Criteria
- `/forte/users/` visually matches Board/Activity's filter + listing + detail chrome.
- Selecting a user updates the detail pane without a full page reload.
- Applying a filter narrows the listing without a full page reload.
- Board and Activity are unchanged and unaffected.
- No database schema changes.

## Addendum: Semantic Filter Categories (post-Step-4 revision)
Step 4 Stage 2 originally shipped an alphabetical A-Z filter (the only schema-free option identified in Step 1, since no role/status field existed). After shipping, the user requested semantic categories instead. This addendum supersedes the alphabetical filter.

**Revised filter categories** (membership flags, not a mutually-exclusive partition — mirrors Activity's `data-paned-activity-view-*` pattern, since a user can match more than one at once):
- **All Users** — approved users, baseline (unchanged from today)
- **New** — NOT (`thread_count >= 1` AND `post_count - thread_count >= 1`)
- **Established** — `thread_count >= 1` AND `post_count - thread_count >= 1` (at least one thread started and one reply made; `post_count` includes replies, `thread_count` counts only root posts)
- **No Threads** — `thread_count = 0` (its own category, kept separate from "New" even though every "No Threads" user is also "New")
- **Recently Active** — any authored post/thread within the last 7 days
- **Not Approved** — fully separate/disjoint bucket; unapproved users appear *only* here, never counted in "All Users" or any of the above

**New data needed:**
- A per-`username_token` last-activity timestamp (`MAX(posts.created_at)` across all identities sharing that token) — no existing query computes this; new but schema-free.
- Pending/unapproved profiles, via the existing `fetchPendingUserDirectoryProfiles()` method (already powers the classic `/users/pending/` page) — new to this directory's data source, but not a new query.

**Core requirement change:** the filter pane is no longer alphabetical; the listing/filter-pane/JS-controller stages (originally Step 4 Stages 2/4/5) are being reworked rather than extended. Detail pane behavior for a "Not Approved" row still needs defining — pending profiles don't have the same visible-threads/posts data an approved user does.
