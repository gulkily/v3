# Forte Activity Feed Step 2 Feature Description

## Problem
Forte has no way to see classic's technical/administrative activity log (source file paths, commit SHAs, signature-verification status, and identity/bootstrap/approval/content events across 5 filtered views) without leaving Forte for classic's `/activity/`. Per Step 1's Decision, the fix is a genuine three-pane paned view mirroring `forte_board.php`'s own structure, not a simplified reader-only list.

## User Stories
- As a Forte user, I want to open an "Activity" view from the board toolbar, so I can see the same category breadth and technical detail classic's `/activity/` provides, without leaving Forte.
- As a user reviewing activity, I want a left-hand filter pane offering the same five categories classic offers (All/Content/Identity/Bootstraps/Approvals), so I can narrow to the events I care about.
- As a user auditing activity, I want a selected item's detail pane to show its source file path, commit SHA, and signature-verification status, so I can verify technical provenance at the same fidelity classic offers.
- As a user browsing content-related activity, I want post/reply-kind items to link into the board using the existing permalink pattern, so I can jump straight to the discussion the event refers to.
- As a keyboard user, I want the filter list and item list to be Tab-reachable and arrow-key navigable, matching every other paned Forte view already shipped.

## Core Requirements
- New route (e.g. `/forte/activity/`) with a `renderForteActivity(string $view)` controller in `Application.php`, following the same `renderStandalonePage(..., 'paned-reader-body', [...], ['/assets/forte.css'])` convention `renderForteBoard()`/`renderForteUserDirectory()` already use.
- **Left pane**: activity-type filter list, structurally the same as `paned_folder_tree.php`, with the five existing view keys (`all`/`content`/`identity`/`bootstrap`/`approval`) as entries. Selected filter is carried in a `?view=` query param, defaulting to `all`.
- **Top-right pane**: list of activity items for the selected filter, produced by calling the existing `fetchActivity($view)` (which applies `activityViewSql()`'s per-view SQL WHERE fragment plus the matching PHP-side `hasBoardTag`/hidden-post filter) verbatim — zero new query or filter logic, same `ACTIVITY_ITEM_LIMIT` and `created_at DESC, post_id DESC, id DESC` ordering classic uses.
- **Bottom pane**: detail view of the selected item, showing `kind`, `label`, `created_at`, the "Author: reply-agent" badge when applicable, and the full technical metadata classic's `source_metadata.php` renders — source path (+ link), commit SHA (+ link, truncated to 12 chars, or "commit unavailable"), and signature-verification status (`sourceSignatureLink()`/`sourceSignatureStatus()`) — same fields, same values, not a paraphrase or subset.
- Items whose classic destination is a board post/thread render their link using the existing `forte_post_permalink` shape (`/forte?selected={threadId}&created_post_id={postId}#post-{postId}`) so clicking them lands in the Forte board with that thread selected and the post highlighted; items whose classic destination is elsewhere (e.g. the feature-flags tool) keep that original destination.
- A new "Activity" toolbar icon button is added to `forte_board.php`'s toolbar, immediately after the existing "Users" button, using the identical pattern committed in e0eb3b7 (`<span class="paned-toolbar-sep">` + `<a class="paned-toolbar-btn">` with a 16×16 stroke-only `currentColor` SVG + `<span>` label), linking to the new route. The new Activity page itself gets a way back to the board (a back link/button, matching how `forte_users.php` returns to the board).
- Filter selection and selected item are reflected in the URL (`?view=&selected=`) and reproduced by the server on first load — same pre-resolution convention `resolveForteBoardSelection()` already uses for the board, so a shared/reloaded Activity link reproduces the same server-rendered state instead of relying on JS correction alone.
- Filter list and item list are Tab-reachable and arrow-key navigable, matching the roving-tabindex convention `paned_folder_tree.php`/`paned_board_thread_list.php` already use elsewhere in Forte.

## Shared Component Inventory
- `fetchActivity()`, `activityViewSql()`, `sourcePathHref()`, `sourceCommitHref()`, `sourceSignatureLink()`, `sourceSignatureStatus()` (all in `Application.php`) — reused verbatim for both the item list and the detail-pane technical metadata; no new backend query or enrichment logic.
- `templates/partials/paned_folder_tree.php` convention — reused structurally for the activity-type filter left pane, swapping tag groups for the five activity views.
- `templates/partials/paned_board_thread_list.php` / `paned_board_content_pane.php` conventions (`data-paned-*` attributes, one-visible-article-at-a-time detail pane) — reused structurally for the new list/detail panes.
- `public/assets/forte.css`'s existing paned-* classes (`.paned-window`, `.paned-toolbar-btn`, `.paned-toolbar-sep`, `.paned-board-layout`, `.paned-folder-tree`, `.paned-list-pane`, `.paned-content-pane`, `.paned-highlight-new`) — reused verbatim; only a new toolbar icon and any activity-specific row/detail styling get added.
- The "Users" toolbar button markup from commit e0eb3b7 — exact structure replicated for the new "Activity" button.
- The `forte_post_permalink` `/forte?selected=&created_post_id=#post-` URL shape and its `.paned-highlight-new` restore-on-load behavior in `paned_board_reader.js` — reused verbatim for post/reply-kind activity items linking into the board.
- `renderStandalonePage()` plus the `forte_users.php` + `renderForteUserDirectory()` pair — reused as the scaffolding convention for the new route/controller/template.
- A new small JS file (sibling to, not a modification of, `paned_board_reader.js`) following the same `data-paned-*` attribute, `URLSearchParams`, and restore-on-load conventions — needed because this page's selection state (activity view + item) is a different shape than thread selection, so the existing script isn't reused directly.

## Simple User Flow
1. From the Forte board, the user clicks the new "Activity" toolbar button.
2. The Activity page opens in the same paned chrome, defaulting to the "All" filter, with the most recent item selected and its detail shown in the bottom pane.
3. The user clicks "Content" in the left filter pane; the list re-renders to show only content-view activity items (the same set classic's `/activity/?view=content` already shows).
4. The user clicks an item in the list; the detail pane updates to show that item's source path, commit SHA, and signature-verification status.
5. For a reply/post-kind item, the user clicks its board link and lands back in the Forte board with that thread selected and the specific post highlighted, via the existing permalink mechanism.
6. The user reloads or shares the Activity page's URL; the same filter and selected item are reproduced by the server.

## Success Criteria
- The five activity-type filters (All/Content/Identity/Bootstraps/Approvals) are available in the left pane, and each shows exactly the same set of items as classic's corresponding `/activity/?view=` filter — same underlying SQL/PHP filter logic, unchanged.
- Selecting any item shows its full technical metadata (source path, commit SHA, signature-verification status) in the detail pane, matching classic's fidelity exactly.
- Post/reply-kind items link into the Forte board via the existing permalink pattern and correctly select and highlight the target post.
- A new "Activity" toolbar button on the Forte board opens this page; the new page provides a way back to the board.
- Filter selection and item selection are reflected in the URL and reproduced by the server on reload or when shared, consistent with `forte_board.php`'s existing convention.
- No new database tables or fields; the new page issues zero new SQL beyond what `fetchActivity()` already runs.
