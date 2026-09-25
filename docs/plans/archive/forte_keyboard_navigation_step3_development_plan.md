# Forte Keyboard Navigation Step 3 Development Plan

## Stage 1 - Board folder tree: roving tabindex and ARIA scaffolding
- Goal: make the folder tree pane a single Tab stop landing on the currently selected folder, with correct listbox/option roles and `aria-selected`, plus a visible focus style.
- Dependencies: none.
- Expected changes: `paned_folder_tree.php` adds `role="listbox"` to the container and `role="option"` + `aria-selected` + `tabindex` (`0` on the selected item, `-1` on the rest) to each `.paned-folder-item`; `paned_board_reader.js` centralizes tabindex/`aria-selected` updates inside the existing `selectFolder()` function so mouse clicks keep it in sync automatically; `site.css` adds a focus-visible style for `.paned-folder-item`, distinct from the existing hover/selected background.
- Verification: manual/automated check — Tab reaches the folder tree landing on the selected item; exactly one item has `tabindex="0"` at a time; `aria-selected` matches the highlighted item; a visible focus ring appears that's distinguishable from hover/selected alone.
- Risks/open questions: keep the roving-tabindex update inside `selectFolder()` itself (not a separate parallel function) so mouse and future keyboard interactions can't drift out of sync.
- Touches: `paned_folder_tree.php`, `paned_board_reader.js`, `site.css`.

## Stage 2 - Board folder tree: arrow-key navigation
- Goal: Arrow Up/Down move focus and selection among folder items while the folder tree has focus.
- Dependencies: Stage 1 (roving tabindex/ARIA scaffolding exists).
- Expected changes: a keydown listener on the folder tree container handles ArrowUp/ArrowDown, computes the adjacent item, calls the existing `selectFolder()` on it, and moves real DOM focus to it; movement clamps at the first/last item (no wrapping, no Home/End - both explicitly out of scope).
- Verification: manual/automated check — with the folder tree focused, Arrow Down/Up moves through items in order, filters the thread list exactly as the equivalent click would, and moves real focus each time; pressing past the first/last item is a no-op, not an error.
- Risks/open questions: only prevent the browser's default arrow-key scroll behavior when a move actually happens, not unconditionally.
- Touches: `paned_board_reader.js` only.

## Stage 3 - Board thread list: same pattern, second pane
- Goal: apply the Stage 1-2 pattern (roving tabindex, ARIA, focus style, arrow keys) to the board view's thread list.
- Dependencies: Stages 1-2 (pattern already proven once).
- Expected changes: `paned_board_thread_list.php` gains the same listbox/option/`aria-selected`/tabindex attributes; `paned_board_reader.js` gains the equivalent keydown handling scoped to currently-visible (non-`hidden`) rows, reusing `selectThread()` as the activation step and keeping roving tabindex in sync when a tag-filter change (via mouse) hides the currently-focused row; `site.css` extends the focus style to `.paned-list-row`.
- Verification: manual/automated check — Tab reaches the thread list on one visible row; Arrow Up/Down moves among visible rows only, matching click-driven selection each time; after switching tag filters, arrow movement still correctly skips newly-hidden rows and the roving tabindex isn't left stranded on a hidden row.
- Risks/open questions: confirm the tabindex-recovery behavior (moving `tabindex="0"` off a row a filter just hid) is triggered from the same code path filtering already uses, not a new parallel check.
- Touches: `paned_board_thread_list.php`, `paned_board_reader.js`, `site.css`.

## Stage 4 - Single-thread reply list: same pattern, second file
- Goal: apply the same pattern to the single-thread Forte reader's reply list.
- Dependencies: Stages 1-3 (pattern already proven twice; this stage ports it to the other JS file).
- Expected changes: `paned_list_pane.php` gains the same listbox/option/`aria-selected`/tabindex attributes; `paned_reader.js` gains the equivalent keydown handling scoped to visible (non-collapsed, non-`hidden`) rows, reusing `selectPost()` as the activation step; focus styling is already covered by Stage 3's shared `.paned-list-row` rule.
- Verification: manual/automated check — Tab reaches the reply list on the currently-selected post; Arrow Up/Down moves among visible rows only (rows under a collapsed branch excluded), matching click-driven selection each time.
- Risks/open questions: reuse the exact same "visible rows" computation the existing Prev/Next toolbar buttons already use, rather than a new parallel one, so the two can't disagree.
- Touches: `paned_list_pane.php`, `paned_reader.js`.

## Stage 5 - End-to-end Tab-order and regression pass
- Goal: confirm the full Tab/Shift+Tab order on both pages matches Step 2's success criteria, and that nothing in Stages 1-4 broke existing mouse-click behavior.
- Dependencies: Stages 1-4 (all panes keyboard-operable).
- Expected changes: none anticipated beyond small fixes if a stray or missing tab stop is found.
- Verification: manual/automated Tab-order walk on `/forte` and `/threads/{id}/forte` confirming the exact sequence from Step 2 (pane → pane → content-pane interactive elements); a role/`aria-selected` snapshot at each stop; a full re-run of the existing mouse-click interaction checks (tag filtering, thread selection, reply collapse/expand) to confirm no regressions.
- Risks/open questions: none expected; this is verification-only unless it surfaces a concrete defect, in which case the fix stays within files already touched above.
- Touches: none expected; any fix stays within Stage 1-4's files.
