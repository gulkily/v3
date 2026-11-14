> **Feature plan:** [Step 1](./guestbook_step1_solution_assessment.md) · [Step 2](./guestbook_step2_feature_description.md) · [Step 3](./guestbook_step3_development_plan.md) · [Step 4](./guestbook_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./guestbook_debug_log.md))

Effort study: `railspike-effort` / `v3/guestbook`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved 2026-11-14; Step 4 in progress on branch `guestbook`. Planning commit `42e32181`.

Cycle estimate: 0.60

# Step 4: Implementation Summary — Guestbook

## Step 4 Entry

2026-11-14: Step 3 projection **0.60 reaffirmed** before implementation; scope v2, revision `b0b706d3`, R1 not re-verified, Claude single exposed rater. Stage originals copied from Step 3: S1 0.15, S2 0.12, S3 0.18, S4 0.08, S5 0.05.

## Stage 1 - Book page lists entries

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `FORUM_GUESTBOOK_ENABLED` in `FeatureFlagRegistry.php` (Experience group, default on) and the two flag lists in `FeatureFlagEvaluatorTest.php`.
  - `guestbookThread` in each site profile entry of `SiteProfileRegistry.php` (`null` for zenmemes until the thread exists), `guestbookThread()` and its validation.
  - `GuestbookPageController.php` and `templates/pages/guestbook.php`; the `/guestbook/` route and application-route entry in `Application.php`.
  - `tests/GuestbookPageTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke GuestbookPageTest FeatureFlagEvaluatorTest`: 449 passed (the guestbook class was not registered on that run); `./v3 test GuestbookPageTest`: 3 passed after registering it. Full suite not run.
  - Covered: oldest-first order, page stability after a new entry, nested replies left out, any page of a spread shows that spread, retraction, empty book, missing thread, out-of-range and malformed pages, flag off, no thread configured.
- Notes:
  - Deviation: a URL page addresses a two-page spread by its left page (`?page=1` shows pages 1 and 2); no page means the newest spread. `PageNavigation::numbered` treats no page as page 1, so the controller builds its own links and reuses only `pageNumber()`.
  - Deviation: the flag defaults on; the page stays off until a site profile names a guestbook thread. Release then needs only the profile entry.
  - Three entries per page. Each entry carries `entry_reply_count` (all replies under it) for Stage 4.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 2 - Sign the book

**Original estimate: 0.12**
**Retrospective: 0.14**

- Changes:
  - `templates/pages/guestbook.php`: the newest spread's right page carries the shared reply form ("Leave a note for whoever reads this next", "Sign the book", parent = thread root, `return_to` = `/guestbook/`). Earlier spreads link to `/guestbook/#sign`.
  - `GuestbookPageController.php`: loads the signing and Outbox scripts the thread page uses.
  - `ComposeAndAccountKeyController::resolveComposeReplyReturnTo`: returns to `/guestbook/` only for a reply to the active site's guestbook thread; any other thread still returns to itself.
  - `tests/browser/guestbook.mjs` and `tests/Support/guestbook_browser_router.php` (the fixture's `root-001` thread as the guestbook).
- Verification:
  - `./v3 test --smoke GuestbookPageTest ComposeAttemptReplayTest`: 468 passed. Full suite not run.
  - New `testSigningTheBookFromTheLastPageReturnsToIt`: form fields, scripts, earlier-spread link, no-JS POST returns to the book, and `return_to` ignored for another thread.
  - `node tests/browser/guestbook.mjs`: passed. Signs with JavaScript at 390 px and without it at 1280 px; both land on `/guestbook/?created_post_id=…`, and both entries show in the thread.
- Notes:
  - The client already honors `return_to` for replies (`canonicalReplyUrl`), so no script changed.
  - The book's form is not an inline thread composer, so the thread's optimistic reply insertion does not run there.
  - Retrospective slightly above the original: the browser fixture needed its own router to name a guestbook thread.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
