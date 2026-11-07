> **Feature plan:** [Step 1](./readable_page_urls_step1_solution_assessment.md) · [Step 2](./readable_page_urls_step2_feature_description.md) · [Step 3](./readable_page_urls_step3_development_plan.md) · [Step 4](./readable_page_urls_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/readable_page_urls`; [host ledger](../effort_estimation_trial.md#cycle-v3readable_page_urls). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60 (revised; Step 2 0.55)

Concepts touched: [collection selection](../../concepts/collection_selection.md) (changed; Stage 8 updates its entry); [static publication](../../concepts/static_publication.md) (used unchanged; Stage 1 confirms that `?page=` skips first-page artifacts).

## Completion Contract

- Normal entry: Next, Previous and First page links on Board, Search, Tags, Recent, Gallery, Activity and profile history; a typed `?page=N`; a typed or shared `?with=<ID>` on every list except Activity.
- End-to-end outcome: each list pages through all its items with readable links. No link contains `position`, base64 or JSON. `with` lands on the page holding its item, scrolled to it.
- Required recovery: a page past the end, zero or not a number, and a `with` ID that is retracted or outside the list each show the list with a First page link. An old `?position=` link opens page 1. None shows a server error.
- Deployment and external checks: no schema or deployment change. High-traffic rewrite rules stay unchanged, because any query string already bypasses first-page artifacts.
- Release condition: smoke and changed PHP tests pass, the changed browser pagination tests pass, and each list is paged by hand with JavaScript on and off. Full suite not run, per project policy.

## Key Risks

- **Moving targets.** A `with` link resolves to a page number that changes as posts arrive. Impact: a cached permanent redirect would send readers to the wrong page. Early validation: `RouteServices` redirects with 303 by default. Mitigation: `with` redirects with 303 to `?page=N#post-<ID>`.
- **Missing anchors.** Recent and board rows carry `data-post-id` but no `id`. Impact: `with` reaches the right page without scrolling. Early validation: Stage 2 lists every row template. Mitigation: give each row `id="post-<ID>"`, the name post cards already use.
- **Two lists on one profile.** Profile history pages threads and posts separately. Impact: a `with` ID could belong to either list. Early validation: Stage 4 checks whether thread roots appear in both lists. Mitigation: a thread ID targets the threads list; any other post ID targets the posts list.
- **Deep page cost.** Offsets scan past earlier rows. Impact: slow deep pages. Early validation: Stage 8 times page 40 of Recent on the 10000-record capacity model. Mitigation: none expected; record the timing.

## Stage 1

**Estimate: 0.12**

- Goal: Recent pages with `?page=N`, as the first vertical slice and the shared page-number contract.
- Dependencies: none.
- Expected changes: `PageNavigation` gains a page parser (`page(array $query): int`, bounded, default 1) and a link builder (`numbered(path, query, page, hasNext)`) that leaves out page 1 and default values. `RecentPostRepository::page` takes a page number and returns items plus whether a next page exists. `RecentPageController` uses both for `/recent/`. Bad page values fall back to the existing First page recovery.
- Verification approach: update `RecentPostTest` and `tests/browser/recent.mjs`; add cases for page 0, `page=abc`, a page past the end and an old `position` link; smoke set.
- Estimate details: driver: defining the shared contract once for six later consumers; uncertainty: low.
- Risks or open questions: Gallery shares this adapter; keep its call working until Stage 3.
- Canonical components/API contracts touched: `src/ForumRewrite/Pagination/PageNavigation.php` (extended), `templates/partials/page_navigation.php` (reused unchanged), `RecentPostRepository` (changed), QDB's page bounding in `QdbBoardPolicy::paginate` (pattern reused, not shared, because it computes totals).

## Stage 2

**Estimate: 0.10**

- Goal: `?with=<ID>` on Recent opens the page holding that post.
- Dependencies: Stage 1.
- Expected changes: `RecentPostRepository` gains a position count (`pageOf(PDO, postId, pageSize): ?int`). The controller redirects with 303 to `?page=N#post-<ID>`, or shows page 1 with First page when the post is missing. `recent_entry.php` rows get `id="post-<ID>"`.
- Verification approach: tests for a post on page 1, on a later page, a retracted post and an unknown ID; browser check that the page scrolls to the row.
- Estimate details: driver: the redirect and anchor; uncertainty: low.
- Risks or open questions: the anchor must not clash with a post card on the same page; Recent shows rows only, so none is expected.
- Canonical components/API contracts touched: `RouteServices` redirect helper (reused), `templates/partials/recent_entry.php` (changed).

## Stage 3

**Estimate: 0.05**

- Goal: Gallery pages with `?page=N` and `?with=<ID>`.
- Dependencies: Stage 2.
- Expected changes: `RecentPageController::gallery` uses the Stage 1 and 2 calls with the images-only filter. Gallery tiles get `id="post-<ID>"`.
- Verification approach: gallery tests for paging and `with`; check page 2 by hand.
- Estimate details: driver: reuse only; uncertainty: low.
- Risks or open questions: none beyond Stage 2.
- Canonical components/API contracts touched: `templates/pages/gallery.php` (changed), `RecentPostRepository` (reused).

## Stage 4

**Estimate: 0.08**

- Goal: profile history pages with `threads_page` and `posts_page`, and accepts `with`.
- Dependencies: Stage 2.
- Expected changes: `ProfileHistoryView` replaces its two position parameters with page parameters and keeps the other list's page in links. `AuthoredContentRepository::visibleIdentityPage` takes a page number and gains a position count. `with` targets the threads list for a thread ID and the posts list otherwise.
- Verification approach: update `ProfilePublicDetailsTest` and `tests/browser/profile_public_details.mjs`; cover paging one list while the other stays put.
- Estimate details: driver: two independent lists in one URL; uncertainty: medium.
- Risks or open questions: see Key Risks, two lists on one profile.
- Canonical components/API contracts touched: `ProfileHistoryView`, `AuthoredContentRepository` (changed).

## Stage 5

**Estimate: 0.08**

- Goal: the Board list pages with `?page=N` and `?with=<ID>`, and leaves out default view and sort.
- Dependencies: Stage 2.
- Expected changes: `BoardPageController` slices its sorted list by page and finds a `with` thread by index. Links omit `sort=newest` and the default view. Board rows get `id="post-<ID>"`.
- Verification approach: update `BoardListTest`; check liked/newest and top sorts by hand.
- Estimate details: driver: several views and sorts; uncertainty: low, since the list is already in memory.
- Risks or open questions: QDB board paging stays on its own route and must not change.
- Canonical components/API contracts touched: `BoardPageController` (changed), `BoardViewOptions` (defaults read, unchanged), `templates/partials/thread_row.php` (changed).

## Stage 6

**Estimate: 0.08**

- Goal: Tags and Search page with `?page=N` and `?with=<ID>`.
- Dependencies: Stage 5, for the board row anchors.
- Expected changes: `ThreadKeysetPage::select` takes a page number and gains a position count, used by `TagPage` and `ThreadSearchPage`. Search links keep `q`.
- Verification approach: update `TagPaginationTest`, `BoardSearchTest`, `tests/browser/tag_pagination.mjs` and `tests/browser/board_search.mjs`.
- Estimate details: driver: one shared adapter, two consumers; uncertainty: low.
- Risks or open questions: activity order can tie; keep thread ID as the tie-breaker so pages stay stable.
- Canonical components/API contracts touched: `ThreadKeysetPage` (changed), `TagPage`, `ThreadSearchPage`, `TagsPageController` (wiring changed).

## Stage 7

**Estimate: 0.08**

- Goal: Activity pages with `?page=N`, and the token code is gone.
- Dependencies: Stages 1 to 6.
- Expected changes: `ActivityService::fetchActivityPage` takes a page number and drops the boundary and local-revision checks. `ActivityPageController` uses the shared links. `PageNavigation` loses `encode`, `decode` and `links`.
- Verification approach: update `StandardActivityPaginationTest` and `tests/browser/standard_activity_pagination.mjs`; search the source for remaining `position` page parameters.
- Estimate details: driver: removing the expiry rules safely; uncertainty: medium.
- Risks or open questions: `ActivityPageController` is the only caller of `fetchActivityPage`, so its signature can change.
- Canonical components/API contracts touched: `ActivityService`, `ActivityPageController` (changed), `PageNavigation` (reduced).

## Stage 8

**Estimate: 0.08**

- Goal: the rule, docs and final checks.
- Dependencies: Stage 7.
- Expected changes: add "URLs are interface" to `PRINCIPLES.md` with its sources. Rewrite `docs/reference/pagination.md` for page numbers and `with`. Update the collection selection concept entry and the consolidation checklist. Record the deep-page timing.
- Verification approach: page every list by hand with JavaScript on and off; time page 40 of Recent; run the smoke set and every changed test; `git diff --check`; review the whole diff for leftover token code.
- Estimate details: driver: manual checks across seven lists; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `PRINCIPLES.md`, `docs/reference/pagination.md`, `docs/concepts/collection_selection.md`, `docs/plans/pagination_consolidation_checklist.md`.

## Study checkpoint

2026-11-07T01:42:32Z, `railspike-effort` / `v3/readable_page_urls`, Step 3 handoff. Step 2 (revised, no backward compatibility) explicitly approved. Scope v3 unchanged. Starting revision `20d24918274561ccb07a29f27f43659747417c04`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.55 → **0.60**, from new understanding, not changed scope: profile history holds two lists in one URL, and eight test files plus five browser tests build position links. Stage-work originals 0.12, 0.10, 0.05, 0.08, 0.08, 0.08, 0.08 and 0.08 are annotations and do not add up to the cycle total. No implementation, measured labor or accepted outcome.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
