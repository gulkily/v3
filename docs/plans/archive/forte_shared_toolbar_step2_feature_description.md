# Forte Shared Toolbar Step 2 Feature Description

## Problem
Forte's three paned views render different toolbars - Board has the full button set, Activity has only "Board" + a disabled "Refresh", and Users has no toolbar at all - so none of them show which view is active or disable buttons that don't apply to it.

## User Stories
- As a Forte user on any of the three views, I want to see the same toolbar buttons in the same place, so switching views doesn't feel like switching apps.
- As a Forte user, I want the button for the view I'm currently on to look pressed/selected, so I always know where I am.
- As a Forte user on Activity or Users, I want New/Reply/Prev/Next visibly disabled rather than absent, so I understand they don't apply here instead of wondering if they're broken or missing.
- As a Forte user on Users, I want the same toolbar as Board/Activity (including a way back to the board and over to Activity), since today Users has no toolbar at all.

## Core Requirements
- A single shared toolbar partial renders the identical button set and order on Board, Activity, and Users: New, Reply, sep, Prev, Next, sep, Refresh, sep, Board, Users, Activity.
- New/Reply/Prev/Next are disabled on Activity and Users (they only apply to the Board's thread list/selection); Board's own existing enable/disable logic for these buttons (e.g., Reply disabled when no thread is selected) is unchanged.
- Refresh stays disabled everywhere, matching its current placeholder state - no behavior change.
- The destination button (Board/Users/Activity) matching the current page is visually depressed/selected and carries `aria-current="page"`; the other two remain plain link buttons.
- Users' page adopts this shared toolbar in place of its current dialog-titlebar-only chrome; its existing flat approved-user list content below is unchanged.

## Shared Component Inventory
- Board's current toolbar markup (`templates/pages/forte_board.php`) - promoted into a new shared partial (e.g. `templates/partials/paned_toolbar.php`), reused by all three page templates instead of copied, per Step 1's Option A recommendation.
- `.paned-toolbar-btn` CSS (`forte.css`) - reused verbatim; a new selected/pressed-state modifier is added for the active destination button, following the same visual convention `.paned-folder-item--selected`/`.paned-list-row--selected` already use elsewhere in Forte.
- `forte_users.php`'s `paned-standalone-window`/`paned-dialog-titlebar` wrapper - dropped in favor of the plain `paned-window` shell Board/Activity already use, since the narrow dialog chrome doesn't accommodate a full-width toolbar; its `paned-standalone-body` content list is otherwise untouched.

## Simple User Flow
1. On Board, the user sees New/Reply/Prev/Next/Refresh as today, plus Board (selected)/Users/Activity destination buttons.
2. The user clicks Activity; the Activity page shows the identical toolbar, with New/Reply/Prev/Next now disabled and Activity shown selected.
3. The user clicks Users; Users now renders the same toolbar (previously had none), with New/Reply/Prev/Next disabled and Users shown selected; the existing user list renders unchanged below it.
4. From any view, clicking Board/Users/Activity navigates there with that view's own button now shown selected.

## Success Criteria
- All three pages render the same toolbar button set and order from one shared partial - no per-page copies.
- The current page's destination button is visually selected and carries `aria-current="page"`; the other two do not.
- New/Reply/Prev/Next are disabled on Activity and Users; Board's existing enable/disable behavior for them is unchanged.
- Users' existing content and back-to-board behavior are preserved, now reachable via the toolbar's "Board" button instead of a standalone back-link.
- No new database fields, tables, or queries - this is template/CSS reuse only.
