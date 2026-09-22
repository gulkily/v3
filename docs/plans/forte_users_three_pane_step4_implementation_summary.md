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

## Stage 4 - Page template + route wiring
- Changes:
  - Rewrote `templates/pages/forte_users.php` from a flat `paned-standalone-body` list to the `paned-window` > `paned-menubar` > toolbar > `paned-board-layout` (filter pane + `paned-panes-stack` holding the list pane and a static "No user selected" detail-pane placeholder) structure `forte_board.php` already uses; added a status-bar line mirroring Board's ("Showing N of M users (LETTER)").
  - Extended `renderForteUserDirectory(string $requestedLetter = '', string $requestedSelected = '')` to compute `letterGroups` via Stage 2's `buildUserDirectoryLetterGroups()` and pass `selectedLetter` / `selectedUserToken` (uppercased/lowercased respectively) to the template.
  - Updated the `/forte/users/` route to pass `$query['letter']` / `$query['selected']` through, mirroring the `/forte/?` route's `tag`/`selected` handling.
  - Scripts argument to `renderStandalonePage()` intentionally left as `[]` — `paned_users_reader.js` is registered in Stage 5 once it exists.
- Verification:
  - `php -l` on both changed files: no syntax errors.
  - `curl /forte/users/` → 200, correct 3-pane HTML, status bar reads "39 users".
  - `curl /forte/users/?letter=I` → the "I" filter entry carries `paned-folder-item--selected`, status bar reads "Showing 3 of 39 users (I)".
  - `curl /forte/users/?selected=ilyag` → the `ilyag` row carries `paned-list-row--selected`.
  - `curl /forte/users/?letter=i&selected=ilyag` → 200 (combined params, case-insensitive letter).
  - `curl /forte/` and `curl /forte/activity/` → both still 200 (Board/Activity unaffected).
- Notes:
  - The detail pane placeholder is unconditional (never server-side "hidden"), even when `?selected=` is present — there is no server-rendered populated state (per the Step 3 decision), so Stage 5's JS must additionally sync the detail pane from the URL on page load, not just on row click.

## Stage 5 - Client-side controller
- Changes:
  - Added `public/assets/paned_users_reader.js`, mirroring the URL-state pattern in `paned_board_reader.js`/`paned_activity_reader.js`: letter click toggles row `hidden` + updates `?letter=` via `history.pushState`; row click updates `.paned-list-row--selected`, updates `?selected=`, and fetches `/api/forte_user_detail?username_token=` (with a client-side cache keyed by token) to inject the returned HTML fragment into the detail pane, with a "Loading…" placeholder and a "Failed to load" fallback, same shape as Activity's commit-detail fetch/inject. `popstate` and initial page load both re-sync filter + selection from the URL.
  - Registered `/assets/paned_users_reader.js` as the scripts argument in `renderForteUserDirectory()`'s `renderStandalonePage()` call.
- Verification:
  - `node --check` on the new JS file: no syntax errors.
  - Confirmed via `curl` that the dev server serves the fingerprinted asset (`/assets/paned_users_reader.<hash>.js`) at 200.
  - Playwright-driven browser session against the local dev server (`http://127.0.0.1:8099/forte/users/`):
    - Clicking the "I" filter narrows the list to 3 visible rows and updates the URL to `?letter=I`.
    - Clicking the `ilyag` row updates the URL to `?letter=I&selected=ilyag`, hides the placeholder, and shows the fetched detail pane (`.paned-content-subject` reads "ilyag").
    - Browser back navigation restores the prior filter/selection state from the URL (verified across two `goBack()` steps).
    - A fresh deep-link load of `/forte/users/?letter=I&selected=ilyag` reproduces the same filtered/selected state without any click.
    - No console or page errors during the session.
  - Re-checked `/forte/` and `/forte/activity/` in the same browser for console/page errors: none (Board/Activity unaffected).
- Notes:
  - No keyboard arrow-key navigation on the filter list (Board's folder tree has this) — out of scope per Step 2's requirements, which only call for filter/select, not full keyboard parity.

## Stage 6 - Regression pass + minor CSS
- Changes:
  - Added `data-paned-users-list-pane` attribute to the root of `templates/partials/paned_user_list.php`.
  - Added a scoped rule block in `public/assets/forte.css` (`[data-paned-users-list-pane] .paned-list-from-head, .paned-list-date-head, .paned-list-from, .paned-list-date`) narrowing the reused From/Date columns to 5rem and right-aligning them for the Threads/Posts counts — the thread list's 9rem/13rem widths (built for a name and a timestamp) were oversized for two short numbers. Scoped by the new data attribute, so Board/Activity's own `.paned-list-from`/`.paned-list-date` rules are untouched.
- Verification:
  - `php -l` on the changed template: no syntax errors.
  - Playwright screenshots at 1280x800 (light and dark `colorScheme`) and 400px-wide viewport of `/forte/users/`: 3-pane chrome renders correctly, columns are proportioned, narrow width stacks/scrolls without horizontal overflow. (This skin doesn't respond to `prefers-color-scheme` — confirmed Board's own screenshot is equally theme-invariant, so this matches existing behavior, not a regression.)
  - Playwright screenshots of `/forte/` and `/forte/activity/` at 1280x800: pixel-comparable to their pre-change appearance, confirming no regression.
  - Playwright screenshot of `/forte/users/?letter=I&selected=ilyag`: filter, listing, and detail panes all render together correctly with real data.
- Notes: none

## Addendum: Semantic Filter Categories (post-Step-4 revision)
Per the Step 2/Step 3 addenda, replacing the alphabetical filter with semantic categories. New Stages 7-13 below.

## Stage 7 - Category data layer
- Changes:
  - Added `fetchUserDirectoryLastActivityByToken(): array` to `src/ForumRewrite/Application.php` — `MAX(posts.created_at)` joined through `profiles.identity_id`, grouped up to `username_token`, filtered to approved profiles and non-hidden posts.
  - Added `buildUserDirectoryCategoryFlags(array $users, array $lastActivityByToken): array` — per user, computes `new`/`established` (established = `thread_count >= 1` AND `post_count - thread_count >= 1`), `no_threads` (`thread_count === 0`), `recently_active` (last activity within 7 days, computed via a UTC `DateTimeImmutable` threshold to match the `Z`-suffixed ISO 8601 timestamps stored in `posts.created_at`).
  - `buildUserDirectoryLetterGroups()` (Stage 2) is left in place, unused by anything new — removal is Stage 13's cleanup.
- Verification:
  - `php -l`: no syntax errors.
  - Reflection-based script against the real 39-user dataset: 27 "new" + 12 "established" = 39 (exhaustive, no overlap — confirmed zero rows where `new === established`); zero rows found `no_threads && established` (would be invalid).
  - Hand-checked three known users: `ilyag` (396 threads, 607 posts → 211 replies, last activity 2 days ago) → established + recently_active; `guest` (36/47 → 11 replies, 14 days ago) → established, not recently_active; `test-user` (25/44 → 19 replies, 23 days ago) → established, not recently_active. All matched expectations by hand.
- Notes: none

## Stage 8 - Semantic filter pane
- Changes:
  - Added `buildUserDirectoryCategoryCounts(int $totalApprovedCount, array $flagsByToken, int $pendingCount): array` to `Application.php` — fixed-order category list (All Users, New, Established, No Threads, Recently Active, Not Approved) with counts, mirroring `renderForteActivity()`'s `$viewCounts` shape. Translates Stage 7's underscore flag keys (`no_threads`, `recently_active`) to hyphenated URL/DOM keys (`no-threads`, `recently-active`) — the only two that need it.
  - Rewrote `templates/partials/paned_users_filter_list.php`: single `foreach` over `$categoryCounts` (was per-letter), `data-paned-user-category` replaces `data-paned-user-letter`, modeled on `paned_activity_filter_list.php`'s fixed-view structure.
- Verification:
  - `php -l` on both changed files: no syntax errors.
  - Reflection script chained Stage 7's outputs through the new counts method: `{all:39, new:27, established:12, no-threads:10, recently-active:1, not-approved:53}` — approved counts match Stage 7's verified numbers exactly; Not Approved (53) matches `fetchPendingUserDirectoryProfiles()`'s own count directly.
  - Rendered the partial via `renderFragment()` with `selectedCategory = 'established'`: the "Established" entry carries `paned-folder-item--selected`, `aria-selected="true"`, `tabindex="0"`; all others don't.
- Notes: none

## Stage 9 - Listing pane rework
- Changes:
  - **Design correction found during implementation**: the Step 3 plan assumed pending users were a disjoint set from approved ones, sourced directly from `fetchPendingUserDirectoryProfiles()`. Real data disproves that — `username_token` isn't unique to pending profiles (locally: "guest" has 10 duplicate pending profiles, "guest2" has 2) and an *already-approved* user can independently accumulate further pending profiles under their own name (locally: "ilyag", who has 396 approved threads, also has 6 pending profiles). Using the raw pending list as-is would make already-listed approved users reappear as if they were separate pending users — the opposite of "not visible anywhere else". Added `fetchNeverApprovedPendingUserDirectoryUsers()` instead: rolls up pending profiles by `username_token` (like `fetchApprovedUserDirectoryUsers()`'s own `SUM(...) GROUP BY`), excluding any token that has ever been approved.
  - Extracted row rendering into two partials mirroring `paned_activity_item_row.php` / `paned_activity_commit_row.php`'s split: `templates/partials/paned_user_row.php` (approved, all 5 category flags explicit) and `templates/partials/paned_user_pending_row.php` (pending, only `data-paned-user-category-not-approved="1"` set, others absent/default-false — same convention as the commit row only setting `data-paned-activity-view-commits`).
  - Rewrote `templates/partials/paned_user_list.php` as the container: loops approved users through `paned_user_row.php`, then pending users through `paned_user_pending_row.php`, appended after — both always rendered server-side, visibility toggled by category like Activity's views (no per-category round trip).
  - Added `.paned-list-row--pending` styling in `forte.css` (muted/italic username) to visually distinguish pending rows.
- Verification:
  - `php -l` on all 4 changed/added files: no syntax errors.
  - Reflection script: `fetchNeverApprovedPendingUserDirectoryUsers()` returns exactly 36 (38 unique pending tokens minus the 2 that overlap with approved — "guest", "ilyag"), zero overlap with the approved token list, and "ilyag" confirmed absent from it.
  - Rendered the list partial for `selectedCategory` = `all`, `established`, `not-approved`: 75 total rows in markup every time (39 approved + 36 pending, both kinds always present); hidden-row counts matched hand-calculated expectations exactly for all three (`all`: 36 hidden = all pending; `established`: 63 hidden = 27 non-established approved + 36 pending; `not-approved`: 39 hidden = all approved).
- Notes:
  - This changes Stage 10's data source: it should use `fetchNeverApprovedPendingUserDirectoryUsers()` for its pending-token fallback, not the raw `fetchPendingUserDirectoryProfiles()` the Step 3 plan named.

## Stage 10 - Pending-user detail pane
- Changes:
  - Extended `handleForteUserDetail()`: when no approved profile matches the token, delegates to a new `handleForteUserDetailPending(string $usernameToken, array $profiles): void` instead of 404ing immediately.
  - **Implementation simplification vs. the plan**: rather than calling `fetchNeverApprovedPendingUserDirectoryUsers()` a second time, `handleForteUserDetailPending()` reuses the `$profiles` array `handleForteUserDetail()` already fetched via `fetchProfilesByUsernameToken()` (every profile, any approval status, for this exact token) — one query instead of two, and the semantics line up exactly: this fallback is only reached when zero of those profiles are approved, matching `fetchNeverApprovedPendingUserDirectoryUsers()`'s own exclusion rule.
  - `handleForteUserDetailPending()` aggregates thread/post counts across all pending profiles sharing the token (handles the "guest"/"guest2" duplicate-profile case from Stage 9) and 404s only when the token has no profiles at all.
  - New partial `templates/partials/paned_user_pending_detail_pane.php`: username, pending profile count, aggregated thread/post counts, "not approved yet" messaging. Read-only — no approve action embedded (that stays on the existing `/users/pending/` admin page, keeping this endpoint out of moderation-action scope).
- Verification:
  - `php -l` on both changed/added files: no syntax errors.
  - `curl /api/forte_user_detail?username_token=onthebus` (single pending profile) → 200, "1 pending profile, 8 threads, 10 posts submitted so far" (matches Stage 9's verified query output for that token exactly).
  - `curl /api/forte_user_detail?username_token=guest2` (2 duplicate pending profiles) → 200, correctly aggregated to "2 pending profiles, 1 thread, 1 post" (matches Stage 9's `pending_profile_count: 2` finding).
  - `curl /api/forte_user_detail?username_token=ilyag` (approved) → still 200 with the original approved-detail fragment, unchanged.
  - `curl /api/forte_user_detail?username_token=totally-unknown-xyz` → still 404.
- Notes: none

## Stage 11 - Page/route wiring rework
- Changes:
  - Added `normalizeUserDirectoryCategory(string $category): string`, mirroring `normalizeActivityView()`'s whitelist-or-fallback-to-'all' pattern.
  - Rewrote `renderForteUserDirectory()`: drops `requestedLetter`, computes `flagsByToken`, `pendingUsers`, and `categoryCounts` up front (all rows/counts render server-side, category switching is purely client-side, same as Activity), normalizes `requestedView` via the new method.
  - Updated the `/forte/users/` route's query param from `letter` to `view`.
  - Rewrote `templates/pages/forte_users.php`: passes `flagsByToken`/`pendingUsers`/`categoryCounts`/`selectedCategory` instead of `letterGroups`/`selectedLetter`; status bar now reads "N users" for All, "N pending users" for Not Approved (no "of N" framing — pending users aren't a subset of the approved total), and "Showing N of M users (Label)" for the rest.
- Verification:
  - `php -l` on both changed files: no syntax errors.
  - `curl /forte/users/` → "39 users". `?view=established` → "Showing 12 of 39 users (Established)", filter pane's "Established" entry carries `paned-folder-item--selected`. `?view=not-approved` → "36 pending users". `?view=bogus` → falls back to "39 users" (normalization confirmed). `?view=not-approved&selected=onthebus` → the pending row carries both `paned-list-row--pending` and `paned-list-row--selected`.
  - `curl /forte/` and `/forte/activity/` → both still 200 (no regression). The old `?letter=I` param is now harmlessly ignored (200, falls back to `all`).
- Notes: none

## Stage 12 - Client-side controller rework
- Changes:
  - Rewrote `public/assets/paned_users_reader.js`: filter-item/row queries switched from `data-paned-user-letter` to `data-paned-user-category`; row visibility now checks `row.getAttribute("data-paned-user-category-" + category) === "1"` (mirroring `paned_activity_reader.js`'s `data-paned-activity-view-*` check exactly) instead of an equality comparison — this also means "All Users" excluding pending rows falls out of the same mechanism automatically (pending rows never carry a `-category-all` attribute), no special-casing needed.
  - URL param renamed `letter` → `view`; `urlForState()` omits it entirely when the category is `all` (was: omit when letter was empty) so default-state URLs stay clean.
  - Status-bar text logic ported from the PHP template's three-way split (All / Not Approved / other), pulling each category's display label from its own filter-item `<span>` text rather than a duplicated JS lookup table.
  - Row-click detail-fetch logic (Stage 5) is untouched — pending rows flow through the exact same `selectUser()`/`showUserDetail()` path as approved rows, since Stage 10's endpoint already handles both.
- Verification:
  - `node --check`: no syntax errors.
  - Playwright browser session against the local dev server: clicking "Established" narrows to 12 rows with `?view=established` and matching status text; clicking "Not Approved" shows exactly the 36 pending rows (confirmed all visible rows carry the pending style) with `?view=not-approved`; clicking a pending row fetches and displays its "Pending approval" detail correctly; switching back to "All Users" hides all pending rows again (0 visible) and restores 39 visible approved rows; a deep-link to `?view=recently-active` reproduces the 1-row filtered state without any click; clicking "New" then browser-back restores the "All Users" URL and status text. Zero console/page errors throughout.
  - Screenshot of `?view=not-approved` with a pending row selected: filter pane, muted/italic pending rows, and the pending detail pane all render together correctly.
- Notes: none

## Stage 13 - Regression pass + cleanup
- Changes:
  - Removed `buildUserDirectoryLetterGroups()` from `Application.php` — confirmed zero remaining references to it, `data-paned-user-letter`, `data-paned-user-row-letter`, `letterGroups`, or `selectedLetter` anywhere in `src/`, `templates/`, or `paned_users_reader.js`.
  - `forte.css` had no letter-specific rules to remove — the alphabetical filter only ever reused generic `paned-folder-item`/`paned-folder-count` classes. The `data-paned-users-list-pane` column-width scoping from Stage 6 is unrelated to letters and stays.
- Verification:
  - `php -l`: no syntax errors.
  - Full curl sweep: `/forte/`, `/forte/activity/`, `/forte/users/`, and `/forte/users/?view=` for all 5 non-default categories (`new`, `established`, `no-threads`, `recently-active`, `not-approved`) — all 200.
  - Playwright sweep (9 pages: Board, Activity, Users default + all 5 categories, Users at 400px width) — zero console errors, zero failed requests, zero page errors on every page.
  - Screenshots confirm: "New" category (1280x800) shows the right 27 rows with correct counts in the filter pane and correct status-bar text; 400px-wide "All Users" view stacks/scrolls cleanly with no horizontal overflow.
- Notes: none

## Stage 14 - Fix detail-pane margins
- Changes:
  - `templates/partials/paned_user_detail_pane.php` and `templates/partials/paned_user_pending_detail_pane.php` (Stages 1/10) wrapped their content in a custom `paned-user-detail-section` class that has no padding rule anywhere in `forte.css` — text sat flush against the pane edge. Board/Activity's content panes instead rely on the shared `.body` class (`padding: 0.75rem`, `public/assets/forte.css:389-395`) for this. Replaced `paned-user-detail-section` with `.body` in both partials (merged into a single wrapping `.body` div per partial, matching Board's one-`.body`-per-post convention) instead of adding a new padding rule for a bespoke class.
- Verification:
  - `php -l` on both files: no syntax errors.
  - Computed style check in a real browser: `.body`'s `padding-left` on a rendered detail pane is `12px` (matches Board/Activity).
  - Screenshots of both an approved user's detail (`ilyag`) and a pending user's detail (`onthebus`) show text properly inset from the pane edge, matching the placeholder's existing spacing.
  - `curl /forte/` and `/forte/activity/` still 200 — no regression (neither uses this class).
- Notes: none

## Outstanding
All 14 stages (6 original + 7 semantic-category addendum + 1 margin fix) implemented, verified, and committed. Feature complete per the Step 2 addendum's revised requirements.
