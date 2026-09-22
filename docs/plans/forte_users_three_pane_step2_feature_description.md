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
- **Detail data source** — `/api/get_profile` endpoint (already used by Board's profile dialog): reused for the new detail pane instead of forking a new payload shape.
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
