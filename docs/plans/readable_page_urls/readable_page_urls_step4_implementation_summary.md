> **Feature plan:** [Step 1](./readable_page_urls_step1_solution_assessment.md) · [Step 2](./readable_page_urls_step2_feature_description.md) · [Step 3](./readable_page_urls_step3_development_plan.md) · [Step 4](./readable_page_urls_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/readable_page_urls`; [host ledger](../effort_estimation_trial.md#cycle-v3readable_page_urls). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60 (Step 3 projection reaffirmed at Step 4 entry)

Branch: `feat/readable_page_urls`, planning commit `44d072c2`. Starting revision `20d24918`.

## Step 4 entry

2026-11-07, Step 3 approved by the maintainer ("God bless you. Approved Step 3"). The Step 3 projection of 0.60 is reaffirmed with no change. Stage identities and original stage-work estimates are copied from the [Step 3 plan](./readable_page_urls_step3_development_plan.md): Stage 1 0.12, Stage 2 0.10, Stage 3 0.05, Stage 4 0.08, Stage 5 0.08, Stage 6 0.08, Stage 7 0.08, Stage 8 0.08. Single exposed Claude rater; copying is not a new rating.

The maintainer did not answer whether to reuse the `plan/readable_page_urls` draft branch. Step 4 uses a fresh branch from `main`, so the planning commit stays planning-only as the gate requires.

## Stage 1 - Recent page numbers

**Original estimate: 0.12**
**Retrospective: 0.12**

- Changes:
  - `PageNavigation` gains `pageNumber` (missing, malformed or out-of-range values read as page 1, capped at 100000), `numbered` (links without page 1 or empty parameters) and `withId` for Stage 2. The token methods stay until Stage 7.
  - `RecentPostRepository::page` takes a page number and returns `items` and `has_next`, reading one extra row instead of probing boundaries.
  - `RecentPageController` and its routes in `Application` read `?page=`. Gallery shares the private page helper, so Gallery also moved to page numbers in this stage; Stage 3 adds its `with` and anchors.
  - A page past the end shows "No posts on this page" with Previous and First page. An old `position` parameter is ignored and shows page 1.
- Verification:
  - `./v3 test --smoke RecentPostTest`: 439 run, 439 passed. Includes new cases for page values `0`, `abc`, `01`, arrays, a page past the end and an old token.
  - `./v3 test GalleryPageTest`: 3 passed.
  - `LocalAppSmokeTest::testRecentStaticArtifactBypassesCursorRequests` renamed to `…BypassesPageRequests`; it now checks that `?page=9` skips the static artifact.
  - `tests/browser/recent.mjs`: fails on the baseline `20d24918` too, at the header Recent link check, because the adaptive nav hides that link. With only the nav-link and nav-icon assertions removed in a temporary copy, it passed: 13 themes at two widths, no-JavaScript keyboard paging to `?page=2`, reload, anchored reply, Back.
  - Full suite not run, per project policy.
- Notes:
  - The browser test's header-link failure is a pre-existing baseline failure, outside this cycle.
  - Invalid page values read as page 1 rather than an error. This matches the Step 2 recovery rule: show the list, never an error.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Recent `with`

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - `RecentPostRepository::pageOf` counts eligible posts newer than the target and returns its page, or null when the post is hidden, retracted, an identity post, a scripture expansion or unknown.
  - `PageNavigation::withLocation` builds `?page=N#post-<ID>`, leaving out page 1.
  - `RecentPageController::withLocation` serves Recent and Gallery. `Application` redirects with 303 before rendering; an unusable or missing ID shows page 1.
  - Recent rows get `id="post-<ID>"`, the name post cards already use. `site.css` outlines a targeted `.thread-row` or `.gallery-tile`.
- Verification:
  - `./v3 test --smoke RecentPostTest GalleryPageTest`: 443 run, 443 passed. New cases check every post on every 3-item page, five excluded IDs, link building and malformed `with` values, plus the rendered redirect and the fallback for an unknown ID.
  - `tests/browser/recent.mjs` gains a `with` check: an ID from page 2 lands on `?page=2#post-<ID>` with that row as `:target`. Passed in the same temporary copy as Stage 1, without the baseline-failing nav assertions.
  - Full suite not run.
- Notes:
  - A missing `with` ID shows page 1 rather than an error, per Step 2. On page 1 there is no First page link to show.
- Estimate details: original scope as planned; uncertainty low; no revision. Smaller than planned because the redirect helper already existed.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 3 - Gallery

**Original estimate: 0.05**
**Retrospective: 0.03**

- Changes:
  - Gallery tiles get `id="post-<ID>"`. `Application` redirects `/gallery/?with=<ID>` through the Stage 2 lookup, after the gallery flag check, with the images-only filter and the 48-tile page size.
  - Page numbers on Gallery already arrived in Stage 1 through the shared helper.
- Verification:
  - `GalleryPageTest` extended: tile anchor, a `with` redirect to `/gallery/#post-gallery-wide`, a text-only post falling back to page 1, and `?page=2` past the end showing "No images on this page" with First page.
  - `./v3 test --smoke GalleryPageTest RecentPostTest`: all passed (see commit).
  - Full suite not run.
- Notes:
  - A post whose image file is missing still counts toward page offsets, because the page query counts posts and the tile step drops missing files afterwards. A page can therefore show fewer than 48 tiles, as it did before this cycle.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 4 - Profile history

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `AuthoredContentRepository::visibleIdentityPage` takes a page number and returns `has_next`; new `visibleIdentityPageOf` finds a post's page in the threads or posts list.
  - `ProfileHistoryView` reads `threads_page` and `posts_page`. Each list's links keep `self=1` and the other list's page when it is above 1, and end in `#threads` or `#posts` as before.
  - `?with=<ID>` on a profile opens the Threads list for a thread the person started and the Posts list otherwise, landing on `#threads-post-<ID>` or `#posts-post-<ID>`. The list prefix keeps anchors unique, since a thread root appears in both lists.
  - `ProfilePageController::withLocation` and the `/profiles/<slug>` route redirect with 303 before rendering.
  - `PageNavigation::withLocation` gains an anchor prefix, and drops a stale page parameter from the kept state.
- Verification:
  - `ProfilePublicDetailsTest`: 6 passed. Covers every post on every page finding its own page, a page past the end, another identity's post returning null, independent list links, an array page value showing page 1, and `with` for a thread and for an excluded post.
  - `tests/browser/profile_public_details.mjs`: passed, 13 themes at two widths plus static and live paging with `threads_page` and `posts_page`, a page past the end with First page, Previous, and offline recovery.
  - `./v3 test --smoke ProfilePublicDetailsTest RecentPostTest GalleryPageTest`: all passed (see commit).
  - Full suite not run.
- Notes:
  - The thread-before-post rule for `with` resolves the "two lists on one profile" risk from Step 3.
- Estimate details: original scope as planned; uncertainty medium as planned; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 5 - Board list

**Original estimate: 0.08**
**Retrospective: 0.07**

- Changes:
  - `BoardPageController::boardList` slices its sorted list by page number; pinned threads appear on page 1 only, as before. The old `listPage` boundary search is removed.
  - Links leave out `view=liked` and `sort=newest`, the route's defaults. Other views and sorts stay in the link.
  - New `listWithLocation` finds a thread's page in the current view and sort; a pinned thread lands on page 1. It returns null for QDB, card-layout sites and threads outside the view. `Application` redirects before rendering.
  - `templates/partials/thread_row.php` rows get `id="post-<root ID>"`. The same partial serves Search and the username page.
- Verification:
  - `BoardListTest`: 10 passed. The paging test now follows numbered links; a new test covers left-out defaults, a page past the end with First page and no pinned list, a malformed page, `with` for an older thread, a pinned thread and an unknown ID.
  - `./v3 test --smoke BoardListTest`: 433 run, 433 passed. `GalleryPageTest RecentPostTest ProfilePublicDetailsTest` rerun: passed.
  - Manual paging of liked/newest and top sorts is part of Stage 8.
  - Full suite not run.
- Notes:
  - A page-content assertion matched `data-pending-thread-position="after"` on the compose form; the test now looks for `?position=` and `&amp;position=` instead.
  - QDB's own `/latest/2` paging is untouched.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 6 - Tags and Search

**Original estimate: 0.08**
**Revised: 0.11** (2026-11-07, new understanding, same scope: the offline reader builds its own saved-tag tokens)
**Retrospective: 0.11**

- Changes:
  - `ThreadKeysetPage::select` takes a page number; new `pageOf` counts listed threads with later activity (thread ID breaks ties). The boundary validation and probe queries are gone.
  - `TagPage` and `ThreadSearchPage` share a private base clause between `page` and `pageOf`. The token `context` methods are removed.
  - `TagsPageController::tag` and `BoardPageController::search` read `?page=`; new `tagWithLocation` and `searchWithLocation` serve `?with=`. Search links keep `q`.
  - `templates/partials/thread_card.php` cards get `id="post-<root ID>"` for tag pages.
  - Shared consumer found during this stage: `public/assets/offline_reader.js` paged saved tag pages with its own base64 tokens. It now reads `?page=N` too, so an online page link opens the same saved page offline. A page past the saved list shows "This page is not in the saved snapshot" with First page.
- Verification:
  - `TagPaginationTest` 6 passed, `BoardSearchTest` passed, `OfflineSnapshotPresentationTest` 8 passed, `OfflineNavigationWorkerTest` passed (its sample URLs now use `page` parameters).
  - `./v3 test --smoke OfflineNavigationWorkerTest OfflineSnapshotPresentationTest TagPaginationTest BoardSearchTest BoardListTest`: 457 run, 457 passed.
  - `tests/browser/tag_pagination.mjs`: passed with and without JavaScript, 16 themes at two widths, and the real offline worker (online page link opens the saved page, past-the-end recovery, saved traversal, reconnect).
  - `tests/browser/board_search.mjs`: passed, 28 theme/width checks and the no-script flow.
  - `tests/Support/tag_page_memory.php` now accepts the card `id`; the bounded-memory test passed.
  - Full suite not run.
- Notes:
  - The offline reader was missing from the Step 2 inventory. It is the Tags list's offline view, so updating it stays inside the approved outcome; leaving it would have left tokens in the address bar offline.
- Estimate details: original 0.08; revised to 0.11 before finishing the stage for the offline reader; uncertainty low after the change.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
