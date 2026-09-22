# Forte Users Three-Pane Layout — Step 3: Development Plan

## Stage 1
- Goal: Serve a single user's detail-pane HTML fragment on demand, mirroring the existing commit-detail fragment endpoint.
- Dependencies: none
- Expected changes:
  - New partial `templates/partials/paned_user_detail_pane.php` (placeholder + populated states) built from data `renderForteUsername()` already assembles: approved profiles, thread/post counts, threads, posts.
  - New route case `/api/forte_user_detail` in `Application.php`'s dispatcher, alongside `/api/forte_commit_detail`.
  - New private method (conceptual) `handleForteUserDetail(array $query): void`, reusing `fetchProfilesByUsernameToken()`, `countVisibleAuthoredRows()`, `fetchVisibleAuthoredThreads()`, `fetchVisibleAuthoredPosts()`; responds via `renderer()->renderFragment()` + `sendJson(['status' => 'ok', 'html' => ...])`, JSON 404 when the token isn't found.
- Verification approach: `curl "/api/forte_user_detail?username_token=<known token>"` locally; confirm 200 JSON with the expected fragment HTML, and 404 JSON for an unknown token.
- Risks or open questions:
  - Directory only lists approved users, so an unresolvable token shouldn't occur in normal use — guard with 404 regardless.
- Canonical components/API contracts touched: extends the existing `/api/forte_commit_detail` fragment pattern (`renderFragment` + `sendJson`); reuses `renderForteUsername()`'s existing data-fetch methods instead of new queries.

## Stage 2
- Goal: Add an alphabetical filter pane so the Users list can be narrowed, mirroring Board's tag folder tree.
- Dependencies: none (sequenced after Stage 1 for review clarity)
- Expected changes:
  - New private method (conceptual) `buildUserDirectoryLetterGroups(array $users): array` returning `[{letter, count}]`, computed from the existing `fetchApprovedUserDirectoryUsers()` result by first character of `username_token` — no new SQL.
  - New partial `templates/partials/paned_users_filter_list.php`, modeled on `paned_folder_tree.php`: "All Users" entry plus one entry per letter with counts.
- Verification approach: render `renderForteUserDirectory()` via a local PHP include/CLI check and confirm letter-group counts sum to the total approved user count.
- Risks or open questions: none
- Canonical components/API contracts touched: `paned_folder_tree.php`'s markup/CSS conventions (`paned-folder-item`, `paned-folder-count`) reused, not forked.

## Stage 3
- Goal: Render the approved user directory as selectable rows instead of a flat `<p>` list.
- Dependencies: none (sequenced after Stage 2 for review clarity)
- Expected changes:
  - New partial `templates/partials/paned_user_list.php`, modeled on `paned_board_thread_list.php`: one `paned-list-row` per user with `data-paned-user-token` / `data-paned-user-letter` attributes and username/thread-count/post-count columns; rows outside the selected letter are `hidden`.
- Verification approach: render `renderForteUserDirectory()` locally and inspect generated row markup/attributes for a few known users.
- Risks or open questions: none
- Canonical components/API contracts touched: `paned_board_thread_list.php`'s row/selection markup conventions (`paned-list-row`, `role="option"`, `tabindex`) reused, not forked.

## Stage 4
- Goal: Replace `forte_users.php`'s flat body with the 3-pane layout and wire request params through.
- Dependencies: Stages 1-3
- Expected changes:
  - Rewrite `templates/pages/forte_users.php` to the `paned-window` > toolbar > `paned-board-layout` (filter pane + `paned-panes-stack` holding the list pane and a detail-pane placeholder) structure `forte_board.php` already uses.
  - Extend `renderForteUserDirectory()` (conceptual signature: `renderForteUserDirectory(string $requestedLetter = '', string $requestedSelected = '')`) to pass `letterGroups`, `selectedLetter`, `selectedUserToken` to the template; detail pane starts in the existing "nothing selected" placeholder state (fetched client-side in Stage 5, no server-side detail prefetch).
  - Update the `/forte/users/` route to pass `$query['letter']` / `$query['selected']` through, mirroring the `/forte/?` route.
- Verification approach: load `/forte/users/`, `/forte/users/?letter=a`, `/forte/users/?selected=<token>` with JS disabled/via curl; confirm correct server-rendered filtering/highlighting with no errors.
- Risks or open questions: none
- Canonical components/API contracts touched: `paned_toolbar.php` (unchanged, `activeView: 'users'`), `paned-board-layout` / `paned-panes-stack` CSS classes reused, not forked.

## Stage 5
- Goal: Make filtering/selecting interactive without full-page reloads.
- Dependencies: Stage 4
- Expected changes:
  - New `public/assets/paned_users_reader.js`, mirroring the URL-state pattern in `paned_board_reader.js` / `paned_activity_reader.js`: letter click toggles row `hidden` and updates `?letter=` via `history.pushState`; row click updates `.paned-list-row--selected`, updates `?selected=`, and fetches `/api/forte_user_detail?username_token=` to swap the returned HTML into the detail pane (same fetch/inject pattern as the existing commit-detail handler).
  - Register `/assets/paned_users_reader.js` as the scripts argument in `renderForteUserDirectory()`'s `renderStandalonePage()` call.
- Verification approach: manual browser test on `/forte/users/` — click letters and rows, confirm the detail pane updates without a reload, the URL reflects state, and back/forward restores prior selection (same check already used for Board/Activity).
- Risks or open questions:
  - Match the detail pane's loading/error states to the existing profile-summary dialog's loading/error pattern for consistency.
- Canonical components/API contracts touched: `/api/forte_user_detail` (Stage 1), URL-state fetch/swap pattern from `paned_activity_reader.js`.

## Stage 6
- Goal: Confirm Board/Activity are unaffected and the new Users pane is visually consistent.
- Dependencies: Stages 1-5
- Expected changes:
  - Add `paned-user-*`-scoped CSS only if user-row columns need widths different from thread rows; no edits to existing Board/Activity selectors in `forte.css`.
- Verification approach: manual walkthrough of `/forte`, `/forte/activity/`, `/forte/users/` at desktop and narrow widths, light and dark; confirm Board/Activity are pixel-identical to pre-change behavior and Users matches their look/feel.
- Risks or open questions: none
- Canonical components/API contracts touched: `forte.css` shared layout classes, extended only if necessary, never forked.
