# Forte Users Three-Pane Layout — Step 4: Implementation Summary

## Stage 1 - User detail fragment endpoint
- Changes:
  - Added `/api/forte_user_detail` route in `src/ForumRewrite/Application.php`'s dispatcher (alongside the other `/api/forte_*` fragment/JSON routes).
  - Added `handleForteUserDetail(array $query): void`, reusing `fetchProfilesByUsernameToken()`, `countVisibleAuthoredRows()`, `fetchVisibleAuthoredThreads()`, `fetchVisibleAuthoredPosts()` (the same data `renderForteUsername()` assembles for `/forte/user/{token}`); responds `{status:'ok', html}` via `renderer()->renderFragment()` on success, `{status:'error', ...}` with HTTP 404 when no approved profile matches the token.
  - Added `templates/partials/paned_user_detail_pane.php`: the populated detail-pane fragment (username, thread/post counts, thread list, post list, link to the full profile page). This stage covers the fragment content only; the page-template placeholder ("no user selected") state is added in Stage 4 alongside the rest of the page layout.
- Verification:
  - `php -l` on both changed/added PHP files: no syntax errors.
  - Started local dev server (`php -S 127.0.0.1:8099 -t public public/router.php`).
  - `curl "http://127.0.0.1:8099/api/forte_user_detail?username_token=ilyag"` (a real approved user from `/forte/users/`) → HTTP 200, `{"status":"ok","html":"..."}` with correctly rendered thread/post rows.
  - `curl "http://127.0.0.1:8099/api/forte_user_detail?username_token=definitely-not-a-real-user"` → HTTP 404, `{"status":"error","error":"user not found"}`.
- Notes:
  - Deviated slightly from the Step 3 wording ("placeholder + populated states" in one partial): kept this partial populated-content-only, since the placeholder is static markup with no data dependency and belongs in the page template built in Stage 4 — simpler than threading an "empty" branch through the fragment endpoint.

## Stage 2 - Alphabetical filter pane
- Changes:
  - Added `buildUserDirectoryLetterGroups(array $users): array` to `src/ForumRewrite/Application.php`, bucketing the approved directory by the first (uppercased) character of `username_token`, with a `#` bucket for any non-letter lead character. Pure function over the existing `fetchApprovedUserDirectoryUsers()` result — no new SQL.
  - Added `templates/partials/paned_users_filter_list.php`, modeled on `paned_folder_tree.php`: an "All Users" entry plus one entry per letter, reusing the `paned-folder-tree` / `paned-folder-item` / `paned-folder-count` CSS classes unchanged.
  - Not yet wired into `renderForteUserDirectory()` or the page template — that happens in Stage 4.
- Verification:
  - `php -l` on both changed/added files: no syntax errors.
  - Scratch script (via `ReflectionClass`, since the method isn't wired into a route yet) called `fetchApprovedUserDirectoryUsers()` then `buildUserDirectoryLetterGroups()` against the real local dataset: 39 total users, letter-group counts summed to 39.
  - Same script rendered `paned_users_filter_list.php` via `renderFragment()` with the real groups and `selectedLetter = 'I'`; inspected output — "All Users" plus per-letter rows render with correct counts, and the "I" entry carries `paned-folder-item--selected` / `aria-selected="true"`.
- Notes:
  - No role/status field exists on `profiles`, so alphabetical grouping is the schema-free filter dimension per the Step 1 recommendation.

## Stage 3 - Listing pane partial
- Changes:
  - Added `templates/partials/paned_user_list.php`, modeled on `paned_board_thread_list.php`: one `paned-list-row` per approved user with `data-paned-user-token` / `data-paned-user-row-letter` attributes, columns for username/thread-count/post-count, `hidden` on rows outside `$selectedLetter`, and `paned-list-row--selected` / `aria-selected` / `tabindex` selection state driven by `$selectedUserToken` (mirroring the thread list's selection convention). The row's letter is derived inline in the template from `username_token`, the same way Board's thread list derives its tags inline rather than requiring precomputed fields.
  - Not yet wired into `renderForteUserDirectory()` or the page template — that happens in Stage 4.
- Verification:
  - `php -l`: no syntax errors.
  - Rendered the partial via `renderFragment()` with the real 39-user dataset, `selectedLetter = 'I'`, `selectedUserToken = 'ilyag'`: 3 rows visible / 36 rows carry `hidden` (matches Stage 2's letter-group count for "I"), exactly one row carries `paned-list-row--selected`, and the `ilyag` row is present with the expected `data-paned-user-token` attribute.
- Notes: none
