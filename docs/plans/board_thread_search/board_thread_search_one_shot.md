# Board thread search — one-shot

Cycle: `v3/board_thread_search`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [board search checklist](../board_search_checklist.md) item 1
Debug mode: enabled ([log](board_thread_search_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/board_thread_search`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.30**

Status: technically complete locally; next action: merge into main and push to origin, which the user authorized on 2026-11-03.

## Request and authorization

Original request, 2026-11-03: "as a user of zenmemes.com, i want to have full-text search of the board available to me through the frontend. we already have some of that feature for qdb site, see if there's anything we can reuse." The user then asked for a checklist with the simplest implementation first, covering only top-level threads like qdb.

Authorization, 2026-11-03: asked whether item 1 could be one-shot, the agent proposed it and asked for the query parameter name. The user replied "yes, query parameter name should be q". The agent treats this as explicit bounded one-shot authority for checklist item 1 only.

Exclusions: reply search, excerpts, filters, an agent plain-text endpoint, FTS5 and any change to qdb's search behavior or URLs. These remain checklist items 2–5. No push, merge or deployment.

## Outcome and work type

Application change. A zenmemes reader opens Search from the app navigation, enters text and gets the matching top-level threads. Matching is the qdb rule: case-insensitive literal substring, applied here to the effective thread title and opening post body. Results use the board's eligibility, so retracted and identity-bootstrap threads never appear. Order is latest activity first. Pages use the shared Previous/Next pagination. The page works without JavaScript and is identical for every viewer.

## Approach and reuse decisions

- **Selection:** a new read-model adapter beside `src/ForumRewrite/ReadModel/TagPage.php`, copying its keyset contract: activity and root ID boundaries, probe queries for directions, hydration through `ThreadRepository::byId`. `TagPage` is the canonical bounded thread selection with shared pagination.
- **Pagination:** shared `PageNavigation` and `partials/page_navigation.php`, per `docs/reference/pagination.md`. qdb's numbered pagination is a recorded legacy exception and is not reused. This corrects the checklist's assumption that `QdbPage`'s count/select flow would be extracted.
- **Match rule:** move qdb's literal-match function out of `QdbPage` into a shared helper used by both, so the two searches cannot drift. qdb output must stay identical.
- **Rows:** the zenmemes list board's `partials/thread_row.php`.
- **Routing:** `/search` stops being rejected on non-qdb profiles; `/?search` stays rejected. The route serves every non-qdb profile, but only the app navigation (zenmemes) gains a link.
- **Navigation:** the Search item goes in the app account menu, not the phone tab bar, which already holds up to six controls. The existing `/search` icon in `public/assets/theme-nav-icons.css` applies to `.nav-link` and covers Word97, Chicago and Midnight.
- **Cleanup:** delete the uncalled `BoardPageController::search()`.
- **No new dependency or table.** The new core query needs read-model version 47 under CONTRIBUTING's query contract, as versions 25, 39, 40 and 43 did for query-only changes.

## Concepts touched

- [Collection selection](../../concepts/collection_selection.md): changed, to describe board thread search.
- [Theme navigation icons](../../concepts/theme_navigation_icons.md): used unchanged.

## Risks

- **qdb regression:** the qdb search has no result-level test. Add one before moving the match rule.
- **Routing contract:** `QdbExperienceRoutingTest` asserts zenmemes rejects qdb paths. Update it deliberately for `/search` only.
- **Retraction:** results are live queries over `threads.retracted_at`, so a retraction removes a thread from results at once. Test it.
- **Scan cost:** literal matching scans every eligible root, as qdb does today. The page bounds hydration to 25 threads, not the scan.
- **High-traffic and offline:** `HighTrafficRoute` does not cover `/search` and the service worker does not serve it offline. No change is needed.

## Verification and recovery plan

- New tests: zenmemes result matching on title and body, case-insensitivity, retracted and bootstrap exclusion, empty query, no results, forward and backward traversal, invalid and foreign positions with First page recovery, prior-version startup rebuild, qdb `/?search` still rejected on zenmemes, and qdb search output unchanged.
- Existing suites: qdb routing, tag pagination, app navigation placement, offline worker, local smoke, then the full PHP suite.
- Rendered checks through the front controller on desktop and phone widths, in the default theme and Word97, Chicago and Midnight icon themes.
- `./v3 rebuild` against a test repository.
- Recovery: an invalid position shows an error with a First page link. Failed navigation uses browser Reload or Back.

Starting revision and branch: `446aa34c7fcc4d8691e9dcc0af9c8b71a4b98175` on `main`; branch `feat/board-thread-search`.

## Study history

- **Intake, 2026-11-03T19:34:25Z, 0.25.** Single exposed rater: Claude (claude-opus-5-5), who had already inspected qdb search, the read model and navigation, and had given a time-based assessment first. Scope v1 is checklist item 1. R1 hashes verified 2026-11-03. The value comes from the checklist's earlier stage-work annotation and is preserved here as the intake projection.
- **Execution entry, 2026-11-03T19:40Z, revised to 0.30.** Same scope v1. Reason: new understanding, not scope change. The read-model version contract applies to this query, and qdb's numbered pagination is not the canonical component, so the shared keyset adapter is needed.
- **Closeout, 2026-11-03.** Projection 0.30 reaffirmed. Retrospective scope assessment **0.30**, also from the single exposed rater, not an independent rating. Corrections: two CSS rules found by the browser check, and one record correction about qdb test coverage. Additions: none. Delivered scope v1 in full. Maintainer comfortable-fit judgment and acceptance are pending. No active-work measurement was collected.

## Dated execution history

- 2026-11-03: Record created before implementation. High-traffic and offline paths checked; no change needed.
- 2026-11-03, correction: the Risks section said qdb search lacked result-level tests. `QdbPageTest` already covers literal matching, activity order and a large body, so it served as the qdb regression check.
- 2026-11-03, discovery: the shared search field's no-JavaScript rule hid every `.board-toolbar__search`. It now applies only to the Users filter, which is script-only. The Users page is unchanged with and without scripts.
- 2026-11-03, discovery: buttons default to full width, so the field and button stacked. A rule keeps a submit button directly inside the board toolbar at natural width.
- 2026-11-03, new request: the user asked to merge into main and push to origin after the merge. Disposition: performed after this commit as integration, not as a change to the delivered scope.

## Outcome

Delivered checklist item 1 as scoped. Zenmemes has Search in the app account menu. `/search?q=` lists listed top-level threads whose effective title or opening body contains the query, case-insensitively and literally. Retracted and identity bootstrap threads never appear. Pages use shared Previous/Next links, and invalid positions offer First page. The page needs no JavaScript and renders the same for every viewer. Members-only sites return the normal 401 identity check to unapproved visitors.

Shared pieces: `ThreadKeysetPage` is extracted from `TagPage` and now serves both lists. `LiteralTextMatch` is the match rule shared with qdb. The uncalled qdb-era `BoardPageController::search()` was replaced. qdb search, its URLs and `/?search` rejection on other profiles are unchanged. Read-model version 47 has a spec entry and a prior-version startup test.

Limitations: replies are not searched, and matching scans every listed root, as qdb does. Search is not in the phone tab bar. Checklist items 2–5 remain.

## Verification

| Check | Result |
| --- | --- |
| `php tests/run.php BoardSearchTest QdbPageTest QdbExperienceRoutingTest TagPaginationTest AppNavigationPlacementTest OfflineNavigationWorkerTest` | Passed, 39 of 39 |
| Full `php tests/run.php` | 1894 of 1899 passed |
| The 5 full-suite failures, run on unchanged `446aa34c` | All 5 fail there too |
| `node tests/browser/board_search.mjs` across all 14 permitted zenmemes themes, at 390px and 1280px | Passed: 28 checks plus the no-JavaScript flow |
| `./v3 rebuild` on a test repository | Wrote version 47; search returned the expected thread |

The five pre-existing failures are three `LocalAppSmokeTest` cases, about private lobby copy, private Forte approval copy and a missing archive filename method, plus `ReadModelBuilderTimingTest` progress counts and `ReactionRecordFormatParityTest` mixed formats.

The browser check used Playwright from `/home/wsl/v3/node_modules/playwright-core` through `PLAYWRIGHT_MODULE`, with Google Chrome. Nothing was added to this repository. It ran through FrontController with PHP 8.1.2 CLI. A physical phone, production data volume, PHP 8.3 FastCGI and deployed static artifacts were not checked.

## Commit/merge/publication status

Completion commit on `feat/board-thread-search`: hash pending, since a record cannot contain its own commit. Merge into main and push to origin follow, as the user authorized. No deployment.

*— Claude (claude-opus-5-5) · 2026-11-03 UTC*
