# Forte Keyboard Navigation Step 2 Feature Description

## Problem
Forte's panes (folder tree, thread/reply list, content pane) aren't reachable or operable by keyboard today — the list rows aren't focusable at all, so Tab skips straight past them to whatever links/buttons happen to exist in the content pane.

## User Stories
- As a keyboard user, I want to Tab through Forte's panes and the interactive elements in the content pane so that I can navigate without a mouse.
- As a keyboard user, I want arrow keys to move the selection within the tags list and thread/reply list when one of them has focus so that browsing feels native, not like a workaround.
- As a screen reader user, I want these lists exposed with real listbox semantics so that my assistive technology correctly announces what's selected and how many options there are.

## Core Requirements
- Each list-shaped pane (the board view's folder tree; the thread/reply list in both views) becomes exactly one Tab stop via roving `tabindex`, landing on whichever item is currently selected.
- Arrow Up/Down inside a focused list pane moves among currently *visible* items only (respecting existing tag-filter/collapse-hidden rows) and immediately applies the same effect the equivalent click already produces — no separate activation step, matching how a single click already both focuses and selects today.
- Tab moves forward from a list pane to the next pane, then into whatever interactive elements already exist in the content pane (author links, the reply-toggle button); Shift+Tab reverses. No artificial stop is added for a pane with nothing interactive in it.
- Each list pane and its items carry correct ARIA roles/state (listbox/option, `aria-selected`) so assistive technology announces selection and option count correctly.
- A focus indicator is visibly distinguishable from the existing hover/selected background styling at all times.
- Out of scope for this pass: Home/End shortcuts, and keyboard-driven expand/collapse of the single-thread reply tree's nested branches (stays mouse-only).

## Shared Component Inventory
- Existing selection logic (`selectFolder()`, `selectThread()` in `paned_reader.js` / `paned_board_reader.js`) — reused as the activation step arrow-key movement calls into, not duplicated.
- Existing `.paned-list-row` / `.paned-folder-item` markup and hidden-row filtering — extended with `tabindex`/ARIA attributes, not restructured.
- Existing `.paned-list-row--selected` / `.paned-folder-item--selected` styling — extended with a new focus-visible style, not replaced.
- New surface: a keydown handler per list-shaped pane for arrow-key navigation and roving-tabindex bookkeeping, added independently to both JS files (the single-thread and board readers already diverge, so this isn't a shared module today).

## Simple User Flow
1. Keyboard user tabs into Forte; the first stop reaches the folder tree (board view) or the reply list (single-thread view), with the current item focused.
2. Arrow Up/Down moves focus and selection among the visible items in that list.
3. Tab moves to the next pane (the thread list, in the board view) with the same behavior, then into the content pane's interactive elements.
4. Shift+Tab reverses through the same stops.
5. A screen reader announces each list as a listbox with the correct selected option and option count as focus moves through it.

## Success Criteria
- Tab/Shift+Tab visits, in order: folder tree (board view only) → thread/reply list → content pane's interactive elements — with no dead stops on non-interactive rows.
- Arrow Up/Down inside a focused list pane moves among visible rows only and produces exactly the same result as clicking that row.
- Focus is visibly indicated at all times, distinguishable from hover/selected background alone.
- Each list pane exposes listbox/option roles with `aria-selected` correctly reflecting the current selection.
- No regression to existing mouse-click behavior in either Forte view.
