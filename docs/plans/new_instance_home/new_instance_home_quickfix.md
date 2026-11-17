# New instance home quickfix record

Cycle: `v3/new_instance_home`; Mode: quickfix (experimental one-shot variant: plan, changes and done report in one commit); Record/index: [this record](./new_instance_home_quickfix.md), [plans index](../README.md)
Debug mode: disabled
Effort study: `railspike-effort` / `v3/new_instance_home`; [host ledger](../effort_estimation_trial.md)
Extended estimates: disabled (single work unit)

**Cycle estimate: 0.15**

Status: locally complete; next permitted action: push, open the PR. Maintainer acceptance pending.

Original request (2026-11-16): try a new experimental Railspike category, quickfix ("writing everything in one commit with a quick fix, including a brief plan, the changes, and the done report"), on the "new instance homepage". It "just needs to look 20% better, including having somewhere to browse to, something that looks like content, and also an option to import-instance from a server (give 2-3 choices)". Follow-ups: "it probably needs a getting started page, and yes, all would be ok. where does all come from in the repo? maybe include this information also?" and (2026-11-17) "please one-shot the quickfix, review existing work which already covers some of it."

Authorization: maintainer in session, 2026-11-17: one-shot this as a quickfix through a PR. Quickfix means one commit, so this record does not get its own planning commit; that departure from the one-shot planning gate is the experiment. The record is still written before implementation. Exclusions: no change to the global board default, no new import mechanism, no new feature flag.

## Existing work reviewed

- **Local start page** (`/local/`, and `/` under `./v3 start`; PR #284, PF-20261117-F01): `templates/pages/local_development.php` already gives a fresh local checkout links to browse, an import form, and "try the forum" links. It only appears on the local development server.
- **Tools → Import** (`/tools/import/`, behind `FORUM_INSTANCE_IMPORT_PAGE_ENABLED`, root operators only): browser-run import through the task worker, with progress.
- **Known instances** (`resources/known_instances.txt`, `KnownInstances::others()`): the import sources offered, never the site's own address. The maintainer chose to list only ZenMemes for now (known_instances record), so the "2–3 choices" become: each known instance, plus any other address.
- **`./v3 import-instance <name|host|url> [--dry-run]`**: the CLI path.

So the gap is a deployed or non-dev instance: `/` opens on an empty Liked view, and there is no getting-started page outside local development.

## Where "All" and "Liked" come from

- `src/ForumRewrite/Application.php`, the `/` and `/threads/` route, passed `$query['view'] ?? 'liked'` (commit `960d80cd`, "Default board to liked newest", 2026-04-25).
- `src/ForumRewrite/Http/BoardViewOptions.php` defines the two pills and their links; `normalizeView()` falls back to `all` only for invalid values.
- `BoardPageController::listState()` treats `liked` as the default and leaves it out of page links.
- `src/ForumRewrite/Tools/BoardListingQuery.php`: Liked means threads carrying a `like` label (anyone's) with a score of 0 or more, so it is the site's curated front page, not the viewer's own likes. The empty-state text "Threads you like will show up here." said otherwise.

## Plan

Outcome and work type: application change.

1. **Board default.** With no `view` in the URL, the board shows Liked; if Liked has no threads besides pinned ones, it shows All instead. An explicit `?view=liked` still shows Liked, empty or not. zenmemes.com keeps its curated front page.
2. **Liked empty text** describes what Liked is.
3. **Getting started page** at `/start/`: what to browse, import choices (each known instance and any other address, as `./v3 import-instance` commands, plus Tools → Import when that page is enabled), and ways to try the forum.
4. **Links to it**: the board shows a Getting started link when it fell back to All, and the "No threads yet" empty state links to it.

Approach and risks:
- The fallback costs a second listing query only when Liked is empty; busy sites never pay it.
- `/` is cached as static HTML. New threads and label/reaction writes already call `StaticArtifactInvalidator::invalidateBoardThread`, which deletes `/index.html`, so the page switches back to Liked once something is liked.
- Two start pages could drift. Shared link cards go in one partial used by both.

Reuse decisions:
- `BoardPageController::fetchBoardThreads()` and `isPinned()` for the fallback check; `BoardViewOptions` unchanged.
- `local_development.php`'s browse and "try the forum" cards move into `partials/getting_started_links.php`, used by both pages.
- `KnownInstances::others()` for import choices; the Tools → Import flag check from the route.
- `card`, `stack`, `button-row`, `nav-link` and `meta` classes; no new CSS.
- `/start/` is not a navigation destination, so no nav icons change.

Completion conditions: on an instance with threads but no likes, `/` shows threads under All with a Getting started link; `/start/` renders with import choices that leave out the site's own address; `?view=liked` still shows the corrected empty text; `/local/` looks as before.

Concepts touched: none (board view and instance import used unchanged).

Verification plan: `BoardListTest` and new `GettingStartedPageTest` cases; `./v3 test --smoke` with those; render `/`, `/start/` and `/local/` locally and look at them. Full suite not run (maintainer runs it).

Starting revisions and branch: `ad5ff5c9`; `feat/new_instance_home`.
Planning commit: none by design (quickfix; see Authorization).

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Done report

Dated execution history:
- 2026-11-17: no `view` now reaches `BoardPageController` as `''`; `board()` and `listWithLocation()` resolve it to Liked, or All when Liked has no unpinned threads (new `hasUnpinned()`). View and sort pills are built after that decision. QDB boards are untouched (they pass explicit views).
- 2026-11-17: `/start/` route and `getting_started.php`; `partials/getting_started_links.php` now also renders `/local/`'s browse and "try the forum" cards. One wording change there: "search your local content" became "search", since the partial serves deployed sites too.
- 2026-11-17: the board toolbar (list layout) and card-layout controls show Getting started only when the default fell back to All; "No threads yet" links to it; the Liked empty text now reads "Threads people like show up here."
- 2026-11-17: `LocalAppSmokeTest` expected the default board's sort links to keep `view=liked` on a fixture with nothing liked. That expectation described the old behavior; updated to `view=all` (correction to a test of changed behavior, not a pre-existing failure).

Outcome: delivered as planned. Import choices are ZenMemes plus "another instance", because the known-instances list holds only ZenMemes by the maintainer's choice; more entries in `resources/known_instances.txt` show up automatically. Not done: a feature flag for the fallback (none needed so far), and running the import from `/start/` itself (Tools → Import and PF-20261117-F06 cover that).

Verification:
- `./v3 test --smoke BoardListTest LocalAppSmokeTest LocalDevelopmentImportTest KnownInstancesTest`: 482 run, 482 passed. New: `BoardListTest::testDefaultBoardShowsAllUntilSomethingIsLiked` (falls back, then returns to Liked after a like record) and `testStartPageOffersBrowsingAndImportChoices` (choices, and the own address left out on zenmemes.com).
- Looked at `/` and `/start/` on a fixture instance with the ZenMemes profile: Gloss at desktop and phone widths, and the dark auto theme. Toolbar wraps cleanly on a phone.
- `python3 tests/browser/local_development.py`: passed (fresh checkout, all offered themes at 390/1280, JS on and off, import failure and retry). It archives `HEAD`, so it ran on the commit before this line was added.
- Full suite not run.

Final review: diff checked for duplication (shared partial instead of a copy of the local page's cards), file scope (board controller, route, three templates, one partial, one new page, tests, this record, plans index, effort ledger, UI copy checklist). No nav destination added, so no icon changes.

Commit/merge/publication status: one quickfix commit on `feat/new_instance_home` (hash reported in the PR; a record cannot hold its own commit's hash). Not merged or deployed.

Study history: intake 0.15 given after reading the board route and the existing import work (exposed, not pristine); closeout retrospective 0.15. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
