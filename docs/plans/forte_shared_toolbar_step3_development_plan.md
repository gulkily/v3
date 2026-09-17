# Forte Shared Toolbar Step 3 Development Plan

## Stage 1
- Goal: Extract Board's toolbar markup into a shared partial and wire it back into Board with a new self-referential "Board" button and selected-state styling, with no other visible change.
- Dependencies: none.
- Expected changes:
  - New `templates/partials/paned_toolbar.php` accepting params: `activeView` (`'board' | 'activity' | 'users'`), `boardControlsEnabled` (bool), `replyEnabled` (bool, board's existing selected-thread logic). Renders: New, Reply, sep, Prev, Next, sep, Refresh, sep, Board, Users, Activity.
  - `templates/pages/forte_board.php` calls the partial with `activeView: 'board'`, `boardControlsEnabled: true`, `replyEnabled: $selectedThreadId !== ''`, replacing its inline toolbar markup.
  - New CSS selected/pressed-state rule in `forte.css` for the active destination button (e.g. an `--selected` modifier class or an `[aria-current="page"]` attribute selector), following the visual convention of `.paned-folder-item--selected`.
- Verification approach: `GET /forte` renders the same buttons as before plus a new selected "Board" button; visual check confirms the selected style is visibly distinct from the two plain destination buttons.
- Risks or open questions:
  - Whether to key selection off a CSS modifier class or `aria-current="page"` - either is fine, pick one and stay consistent with existing naming.
- Canonical components/API contracts touched: `.paned-toolbar-btn` CSS convention (`forte.css`); Board's toolbar markup, now sourced from `paned_toolbar.php`.

## Stage 2
- Goal: Replace Activity's ad hoc toolbar with the shared partial, correctly disabled and selected.
- Dependencies: Stage 1.
- Expected changes:
  - `templates/pages/forte_activity.php` calls `paned_toolbar.php` with `activeView: 'activity'`, `boardControlsEnabled: false`, replacing its current inline "Board" + "Refresh" markup.
- Verification approach: `GET /forte/activity/` - New/Reply/Prev/Next render `disabled`, Refresh stays disabled, Board/Users render as plain links, Activity renders selected.
- Risks or open questions: none expected.
- Canonical components/API contracts touched: `paned_toolbar.php` (consumed, not modified).

## Stage 3
- Goal: Give Users the shared toolbar, dropping its standalone dialog chrome in favor of the plain `paned-window` shell, without changing its existing list content.
- Dependencies: Stage 1.
- Expected changes:
  - `templates/pages/forte_users.php` outer wrapper changes from `paned-window paned-standalone-window` to `paned-window`; adds the `paned-menubar` row and `paned_toolbar.php` with `activeView: 'users'`, `boardControlsEnabled: false`.
  - Removes the now-redundant `paned-dialog-titlebar` "Users" heading and the `<p class="paned-standalone-back">` link (both superseded by the toolbar's selected "Users" button and its "Board" button, respectively).
  - `paned-standalone-body` list content (the approved-user list itself) is otherwise unchanged.
- Verification approach: `GET /forte/users/` - toolbar renders with Users selected and New/Reply/Prev/Next disabled; existing user list renders unchanged; clicking the toolbar's Board button returns to `/forte`.
- Risks or open questions:
  - `.paned-standalone-window`'s narrow centered width (`min(34rem, 94vw)`) is dropped along with the wrapper class - confirm during manual check whether the list should keep a narrower content column or use the full toolbar width; adjust CSS only if it reads poorly.
- Canonical components/API contracts touched: `paned_toolbar.php` (consumed); `.paned-standalone-window`/`.paned-dialog-titlebar` CSS (`forte.css`) - usage removed from this page only, rules left in place for any other page still using them.

## Stage 4
- Goal: Regression pass confirming no behavioral change to Board's existing toolbar actions and correct inert/disabled behavior on Activity and Users.
- Dependencies: Stages 1-3.
- Expected changes: none anticipated beyond small fixes if a defect surfaces.
- Verification approach: manual click-through on all three pages confirming New/Reply/Prev/Next/Refresh behave identically to pre-change Board behavior where enabled and are inert where disabled; keyboard focus order across the toolbar is sane on all three pages; full test suite re-run confirming no regressions.
- Risks or open questions: none expected; verification-only unless a concrete defect surfaces.
- Canonical components/API contracts touched: none expected.
