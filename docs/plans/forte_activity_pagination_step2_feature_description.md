# Forte Activity Pagination — Step 2: Feature Description

## Problem
The Forte Activity feed silently truncates every filtered view at 100 items, hiding older activity with no indication or way to see further, while loading everything at once isn't usable either.

## User Stories
- As a moderator reviewing activity, I want to load older items on demand so that I can see history beyond the initial 100 without a giant page load.
- As a user browsing a specific filter (e.g. Approvals), I want to page through that filter's history so I can find older items relevant to that view.
- As any Activity feed visitor, I want a visible indicator when more items exist so that I know the list isn't the whole picture.

## Core Requirements
- Each of the 5 filtered views (All, Visible Content, Identity, Bootstraps, Approvals) can be paged independently past its initial 100-item load.
- A "Load more" control appears when a view has additional items beyond what's currently loaded, and disappears once that view is exhausted.
- Loading more items appends to, rather than replaces, the currently visible list.
- Each view's load progress (how far it has paged) persists when switching between filters, consistent with the feed's existing per-item view-membership behavior.
- No single load fetches more than the existing page-size cap (100) worth of new items.

## Shared Component Inventory
- `fetchActivity()` (`Application.php`) — existing per-view activity query; feature extends its usage (adds paging) rather than forking a new query.
- `paned_activity_item_list.php` — existing list-pane partial that renders items; feature extends it to append additional pages rather than introducing a new list renderer.
- `paned_activity_reader.js` — existing client behavior script (filter/select/prev-next); feature extends it with load-more/append behavior, since no `fetch()` call currently exists anywhere in this feature.
- No existing "Load more"/offset-pagination pattern exists in the codebase to reuse. The closest analogues (`sqlite_viewer.js`'s pager, `tags.php`'s static "N of M" preview) operate on already-loaded data and aren't structurally reusable here — this feature needs new supporting surface, to be decided in Step 3.

## Simple User Flow
1. User opens the Forte Activity page; up to 100 items load per view, as today.
2. If a view has more items beyond the initial 100, a "Load more" control appears at the bottom of the list pane for that view.
3. User clicks "Load more"; the next page of items for the current view is fetched and appended below existing rows.
4. User can keep clicking "Load more" until that view's history is exhausted, at which point the control disappears.
5. Switching filters preserves each view's own load progress.

## Success Criteria
- No view silently truncates without an affordance to see more.
- Initial page load size and shape are unchanged (still ≤100 items per view, same merge/flag behavior).
- Users can reach items older than the current 100-item wall through an explicit, bounded action.
- Paging one view does not trigger loading data for other views.
