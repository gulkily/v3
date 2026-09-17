# Forte Activity Feed Step 3 Development Plan

## Stage 1 - Backend route, controller, and server-side view/selection resolution
- Goal: new route/controller that fetches `fetchActivity('all')` once (the unfiltered superset) and computes, per row, which of the 5 classic views (all/content/identity/bootstrap/approval) it belongs to, reusing the exact same predicates (`hasBoardTag()`, `isHiddenBootstrapBoardTagsJson()`) `fetchActivity()`'s own PHP-layer filter already applies - not a re-derivation that could drift from classic's behavior. Resolve `?view=` against the 5 valid keys (default `all`) and `?selected=` against the resolved view's item list (default: most recent item in that view), mirroring `resolveForteBoardTag()`/`resolveForteBoardSelection()`'s resolve-with-safe-fallback convention.
- Dependencies: none.
- Expected changes: new route in the dispatch table (`^/forte/activity/?$`) alongside the existing `^/forte/?$`; new `renderForteActivity()` in `Application.php` next to `renderForteBoard()`/`renderForteUserDirectory()`; a small per-row flag helper reusing `hasBoardTag()`/`isHiddenBootstrapBoardTagsJson()` so each row carries its 5-view membership; new template `templates/pages/forte_activity.php` scaffolding the static three-pane markup (no client JS yet) via `renderStandalonePage(..., 'paned-reader-body', [], ['/assets/forte.css'])`.
- Verification: `GET /forte/activity/` server-renders all 3 panes with the "All" filter selected and the most recent item's full detail (including technical metadata) in the bottom pane; `GET /forte/activity/?view=content&selected={id}` resolves to that view/item, falling back safely to `all`/most-recent on an invalid `view` or `selected`.
- Risks/open questions: **explicit design decision** - view switching will be client-side over one fetched superset (mirroring the existing tag-filter/sort-column client-side conventions already in `forte_board.php`), not a server round-trip per view. This is what makes reusing `paned_folder_tree.php`'s structure for the filter pane meaningful; Stage 1's flag computation is what makes that possible without duplicating or drifting from classic's per-view filter logic.
- Touches: `Application.php`, new `templates/pages/forte_activity.php`.

## Stage 2 - Left filter pane, list pane, and detail pane markup
- Goal: render the three panes' actual content: left pane reusing `paned_folder_tree.php`'s structure for the 5 view entries; list pane reusing `paned_board_thread_list.php`'s row conventions plus the Stage 1 per-row view-membership flags as data attributes; detail pane reusing `source_metadata.php`'s exact fields/markup for the selected item's technical metadata (source path + link, commit SHA + link, signature-verification status), plus `kind`/`label`/`created_at`/the "Author: reply-agent" badge.
- Dependencies: Stage 1.
- Expected changes: new partials (e.g. `templates/partials/paned_activity_filter_list.php`, `paned_activity_item_list.php`, `paned_activity_detail_pane.php`) - decide during implementation whether `paned_folder_tree.php` is generalized to serve both tag groups and activity views, or a sibling partial is cleaner; items whose classic destination is a board post/reply render via the existing `forte_post_permalink` `/forte?selected=&created_post_id=#post-` shape; other kinds keep their classic destination link (e.g. the feature-flags tool).
- Verification: manual check - each of the 5 filter entries, list rows, and the detail pane (with technical metadata matching classic's `/activity/` output for the same item, field-for-field) render correctly for a representative item of each kind; a post/reply-kind item's link matches the exact URL shape `forte_post_permalink` already produces.
- Risks/open questions: none expected beyond matching classic's exact metadata formatting (truncated 12-char commit SHA, "commit unavailable"/signature-status strings) verbatim rather than paraphrasing it.
- Touches: `Application.php` (view assembly), new partials under `templates/partials/`.

## Stage 3 - Toolbar entry point and way back
- Goal: add the "Activity" toolbar icon button to `forte_board.php` (after "Users", matching commit e0eb3b7's exact markup pattern - separator + `<a class="paned-toolbar-btn">` + inline SVG + label) linking to `/forte/activity/`, and a way back to the board from the new page.
- Dependencies: Stage 1 (route must exist to link to).
- Expected changes: `templates/pages/forte_board.php` toolbar gains the new button; `templates/pages/forte_activity.php` gains a back link/button to `/forte`, matching `forte_users.php`'s existing back-link convention.
- Verification: manual check - clicking "Activity" from the board opens the new page; clicking back returns to the board.
- Risks/open questions: none expected.
- Touches: `templates/pages/forte_board.php`, `templates/pages/forte_activity.php`.

## Stage 4 - Client-side filter switching, item selection, and keyboard nav
- Goal: clicking a left-pane filter instantly shows/hides list rows client-side using each row's Stage 1 view-membership flags (no reload); clicking a list row selects it and shows its detail pane while hiding the rest, matching `forte_board.php`'s single-visible-article convention; both the filter list and item list are Tab-reachable and arrow-key navigable via the same roving-tabindex convention used elsewhere in Forte.
- Dependencies: Stages 1-3.
- Expected changes: new script `public/assets/paned_activity_reader.js` (a sibling to `paned_board_reader.js`, not a modification of it, since the underlying data shape differs) wiring filter clicks, row selection/show-hide, and roving tabindex for both the filter list and item list.
- Verification: manual check - switching filters instantly updates the visible list with no network request; selecting an item updates the detail pane; arrow keys move focus/selection correctly within both the filter list and the item list, skipping hidden rows.
- Risks/open questions: none expected; follows the same DOM-attribute-driven pattern already proven in `paned_board_reader.js`.
- Touches: `public/assets/paned_activity_reader.js` (new), `templates/pages/forte_activity.php` (script include).

## Stage 5 - URL sync and permalink integration verification
- Goal: filter/selection state is reflected in the URL (`?view=&selected=`) via `history.pushState` and restored correctly on load/back/forward, mirroring `forte_board.php`'s URL-sync and `restoreSelectionFromUrl()` conventions; confirm post/reply-kind items' board links correctly select and highlight the target post via the existing, unmodified permalink mechanism.
- Dependencies: Stages 1-4.
- Expected changes: `paned_activity_reader.js` gains URL-building/restore-on-load logic analogous to `paned_board_reader.js`'s.
- Verification: clicking a filter/item updates the URL; reloading or sharing that URL server-renders the same state (via Stage 1's resolver); back/forward restores prior filter+selection; clicking a post/reply item's board link lands in the Forte board with the correct thread selected and post highlighted.
- Risks/open questions: none expected; verification-only for the permalink leg since that mechanism is untouched by this feature.
- Touches: `public/assets/paned_activity_reader.js`.
