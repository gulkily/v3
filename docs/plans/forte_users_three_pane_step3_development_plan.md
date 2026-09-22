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

## Addendum: Semantic Filter Categories (post-Step-4 revision)
Supersedes the alphabetical filter (Stages 2/4/5 above) per the Step 2 addendum. New Stages 7-13 below.

## Stage 7
- Goal: Compute the data the semantic categories need: a per-`username_token` last-activity timestamp, and per-user category flags (new/established/no_threads/recently_active).
- Dependencies: none
- Expected changes:
  - New private method (conceptual) `fetchUserDirectoryLastActivityByToken(): array` — `MAX(posts.created_at)` joined through `profiles.identity_id`, grouped up to `username_token` (a user with multiple approved identities takes the max across all of them). New query, no schema change.
  - New private method (conceptual) `buildUserDirectoryCategoryFlags(array $users, array $lastActivityByToken): array` — per user, computes `new` (NOT (`thread_count >= 1` AND `post_count - thread_count >= 1`)), `established` (the inverse), `no_threads` (`thread_count === 0`), `recently_active` (last activity within 7 days of now).
  - Removes `buildUserDirectoryLetterGroups()` (superseded; no longer called after Stage 11).
- Verification approach: reflection-based script (as used in original Stage 2) computing flags/timestamps against the real local dataset; spot-check 2-3 known users' flags by hand against their actual thread/post counts and most recent post date.
- Risks or open questions:
  - "Established" requires a reply specifically, not just 2+ posts — confirm the `post_count - thread_count >= 1` reading of "1 post + 1 comment" is right before Stage 9 renders it as a label.
- Canonical components/API contracts touched: none new; reuses `fetchApprovedUserDirectoryUsers()`'s existing `thread_count`/`post_count` columns.

## Stage 8
- Goal: Replace the alphabetical filter pane with a semantic-category one, plus a separate "Not Approved" entry.
- Dependencies: Stage 7
- Expected changes:
  - Rewrite `templates/partials/paned_users_filter_list.php` from per-letter entries to fixed category entries (All Users, New, Established, No Threads, Recently Active, Not Approved), modeled on `paned_activity_filter_list.php`'s fixed-view structure rather than `paned_folder_tree.php`'s per-tag structure.
  - Counts per category come from Stage 7's flags (approved categories) plus `count(fetchNeverApprovedPendingUserDirectoryUsers())` for Not Approved (Stage 9 correction: rolled up and excludes tokens that have ever been approved, not the raw `fetchPendingUserDirectoryProfiles()`).
- Verification approach: render the partial via `renderFragment()` with real counts; confirm each category count against a manual tally from the Stage 7 script's output.
- Risks or open questions: none
- Canonical components/API contracts touched: `paned_activity_filter_list.php`'s fixed-category markup/CSS conventions, reused not forked.

## Stage 9
- Goal: Replace per-row letter attributes with per-row category-membership flags, and list Not Approved users alongside (not instead of) approved ones.
- Dependencies: Stage 7
- Expected changes:
  - Rewrite `templates/partials/paned_user_list.php`: drop `data-paned-user-row-letter`; add `data-paned-user-view-new`, `-established`, `-no-threads`, `-recently-active` flags (mirroring Activity's `data-paned-activity-view-*` convention), each `"1"`/absent per Stage 7's flags. A row can carry multiple flags at once.
  - Append pending rows from `fetchNeverApprovedPendingUserDirectoryUsers()` (Stage 9 correction, see summary) after the approved rows, each carrying `data-paned-user-category-not-approved="1"` and no other flags, visually distinguished (e.g. a muted/pending style) and hidden unless the Not Approved category is selected.
  - "All Users" continues to mean approved users only; pending rows are never visible under "All Users".
- Verification approach: render with real data; confirm approved-row flag combinations against Stage 7's script output, and confirm pending rows appear only when Not Approved is selected.
- Risks or open questions: none
- Canonical components/API contracts touched: `paned-list-row` markup conventions reused; flag-attribute convention borrowed from `paned_activity_item_row.php`.

## Stage 10
- Goal: Give a Not Approved row a meaningful detail-pane view (pending profiles have no visible threads/posts the way approved users do).
- Dependencies: Stage 9
- Expected changes:
  - Extend `handleForteUserDetail()`: when no approved profile matches the token, fall back to `fetchNeverApprovedPendingUserDirectoryUsers()` (Stage 9 correction); if a pending profile matches, render a new partial `paned_user_pending_detail_pane.php` (username, pending profile count, thread/post counts so far) instead of 404. Still 404 when neither approved nor pending matches.
  - Read-only: no approve action embedded here (that stays on the existing `/users/pending/` admin page) — keeps this endpoint from taking on moderation-action scope.
- Verification approach: `curl /api/forte_user_detail?username_token=<known pending token>` → 200 with pending-shaped HTML; confirm an approved token still returns the Stage 1 fragment unchanged.
- Risks or open questions: none
- Canonical components/API contracts touched: extends the Stage 1 `/api/forte_user_detail` contract rather than forking a second endpoint.

## Stage 11
- Goal: Wire the reworked partials into the page template and route, replacing letter-based params with category-based ones.
- Dependencies: Stages 7-10
- Expected changes:
  - Rewrite `renderForteUserDirectory()`: drop `requestedLetter`; all approved+pending rows and all category flags/counts render server-side up front (like Activity's views — switching categories is purely client-side, no new server round trip per category).
  - Update the `/forte/users/` route's query param from `letter` to `view` (mirroring Activity's `?view=`).
  - Update `templates/pages/forte_users.php` to pass the new category/pending data instead of `letterGroups`.
- Verification approach: `curl /forte/users/`, `/forte/users/?view=established`, `/forte/users/?view=not-approved`, `/forte/users/?selected=<token>&view=recently-active`; confirm correct server-rendered highlighting per the FDP's no-JS-baseline convention.
- Risks or open questions: none
- Canonical components/API contracts touched: `?view=` param convention reused from Activity's route.

## Stage 12
- Goal: Rework the client-side controller for flag-based filtering instead of letter-based.
- Dependencies: Stage 11
- Expected changes:
  - Update `public/assets/paned_users_reader.js`: replace single-letter visibility toggling with flag-attribute checks (`row.getAttribute("data-paned-user-view-" + category) === "1"`), mirroring `paned_activity_reader.js`'s `selectFilter()`; keep the existing URL-state/detail-fetch logic from Stage 5, updating the `letter` param name to `view`.
- Verification approach: Playwright browser session against the local dev server — click each category, confirm the right rows show/hide (including Not Approved rows only appearing under that category), confirm detail-pane fetch still works for both an approved and a pending row selection, confirm back/forward URL restoration.
- Risks or open questions: none
- Canonical components/API contracts touched: none new.

## Stage 13
- Goal: Confirm Board/Activity remain unaffected and remove now-dead alphabetical code.
- Dependencies: Stages 7-12
- Expected changes:
  - Remove `buildUserDirectoryLetterGroups()` and any now-unreferenced letter-specific markup/CSS (the `data-paned-users-list-pane` column-width CSS from Stage 6 stays — it's about column proportions, not letters).
- Verification approach: full curl + Playwright sweep of `/forte`, `/forte/activity/`, `/forte/users/` (all categories) with screenshots, same as original Stage 6.
- Risks or open questions: none
- Canonical components/API contracts touched: none new.
