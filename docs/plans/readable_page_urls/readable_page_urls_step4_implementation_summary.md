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
