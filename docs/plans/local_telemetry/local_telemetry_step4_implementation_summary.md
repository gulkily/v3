> **Feature plan:** [Step 1](./local_telemetry_step1_solution_assessment.md) · [Step 2](./local_telemetry_step2_feature_description.md) · [Step 3](./local_telemetry_step3_development_plan.md) · [Step 4](./local_telemetry_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; [debug log](./local_telemetry_debug_log.md))

Effort study: `railspike-effort` / `v3/local_telemetry`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.90** (Step 4 entry, reaffirmed from Step 3)
**Retrospective cycle scope: 1.00** (see the Stage 7 closeout)

Branch `feat/local_telemetry` from main `459d212d`; planning commit `707d3d99`. Stage originals below are copied from the approved Step 3 (scope v4/B, R1, Claude single exposed rater); copying is not a new rating.

## Stage 1 - Report store, config path and flag

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes:
  - `UsageReportStore` (`src/ForumRewrite/UsageReports/`): reports table (received time, username or null, body, size) and an upload-window table keyed by a SHA-256 client hash, pruned after an hour. `add()` checks size (100 KB), then the client limit (10 per hour, with seconds until retry), then the store ceiling (50 MB) inside one `BEGIN IMMEDIATE` transaction. `reports(?string $uploader)` (null all, '' anonymous) and `uploaders()` feed the summary.
  - `UsageReportResult` (stored, too large, rate limited, store full) and `UsageReportDatabaseConfig` (`state/private/usage_reports.sqlite3`, overridable).
  - `USAGE_REPORTS_DATABASE_PATH` in `PrivateConfigSchema` (definition and example); `FORUM_USAGE_REPORTS_ENABLED` flag, off by default, in the Experience group.
- Verification:
  - `./v3 test UsageReportStoreTest FeatureFlagEvaluatorTest PrivateConfigSchemaTest FeatureFlagsBehaviorTest`: new store tests pass (paths, attribution and filters, size refusal not counted, per-client limit and Retry-After, full store keeps reports, no raw IP stored).
  - `./v3 test` (smoke set): passes except two failures that reproduce on main `459d212d` without this change: `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder` and `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`.
- Notes:
  - Reuse: config and store follow `VisitorStatisticsDatabaseConfig`/`VisitorStatisticsStore`; the flag follows `POST_RECORDS_ENABLED`. Nothing reads the flag yet.
  - Production runbook entry for the new path waits for Stage 7 with the other docs.
- Estimate details: original scope as approved Step 3 Stage 1; uncertainty low; no revisions. Retrospective: scope matched the plan.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

Revision (Stage 2): the Stage 1 verification above was wrong about `UsageReportStoreTest`. The runner only runs files listed in `tests/run.php`, so it reported "No tests matched" and the store tests did not run in Stage 1. Registered in Stage 2 and run there: all five pass.

*— Revised by Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 2 - Redacted page kind

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `UsagePageKind::fromPath(string $path): string` with `UsagePageKind::WORDS`, an allowlist of fixed route words taken from the router, Tools routes and template links. Every other segment becomes `*`; queries and fragments are dropped.
  - `TemplateRenderer` passes `usageReportsEnabled`, `usagePageKind` and `usagePageWords`; with the flag on, `layout.php` adds `data-usage-page` and `data-usage-words` to `<html>` beside the existing theme attributes.
  - Registered `UsageReportStoreTest`, `UsagePageKindTest` and `UsageReportsPageTest` in `tests/run.php`.
- Verification:
  - `./v3 test UsageReportStoreTest UsagePageKindTest UsageReportsPageTest TestFileRegistrationTest`: 11 passed. The redaction test covers all 23 parameterized routes in `Application.php` plus `/forte/threads/*/replies` and docs paths; the page test checks attributes appear with the flag on and are absent with it off.
  - `./v3 test` (smoke set): passes except `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, which also fails on main. `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder` passed this run, so it looks intermittent rather than broken by Stage 1.
- Notes:
  - Static and cached pages render without a request path, so they carry only the words; the browser computes the page kind from `location.pathname` (Stage 3).
  - A username or tag that equals a route word (for example a user named `about`) would survive redaction. Accepted: it is one word and the visitor reviews the log before upload.
- Estimate details: original scope as approved, plus registering Stage 1's test in the runner; uncertainty low; no revisions.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 3 - Browser recorder

**Original estimate: 0.15** (Step 3 revision for Step 2 Revision 1; first value 0.12)
**Retrospective stage work: 0.15**

- Changes:
  - `public/assets/usage_log.js`, loaded by `layout.php` only with the flag on. It keeps `forum_usage_log` (recording state and entries) in localStorage behind guarded reads and writes, like `nav_slots.js`.
  - Records a view per page load (and on back/forward restores), and in the capture phase every click on `a`, `button`, `summary` or `[role=button]` and every form submit. A click holds region, name and destination page kind.
  - Regions, first match wins: account menu, Tools bar, site header (navbar), list rows, post cards; then composer (a form with a textarea); then footer, main, page. Names come from `data-tab`/`data-tone`/`data-action`, else `aria-label`/`title`/text, cut to 40 characters.
  - Log text format `usage-log v1`, one tab-separated line per entry with seconds since the previous entry. Capped at 2,000 entries and 30 days. Nothing is recorded on `/tools/usage-log` itself.
  - `window.forumUsageLog` (`available`, `kind`, `state`, `text`, `setRecording`, `clear`) for the Stage 4 page.
- Verification:
  - New `tests/browser/usage_log.mjs` passed: off by default; navbar click with destination `/about`; menu toggle in the menu region; an unhooked post link named by its text with destination `/threads/*`; composer submit; no typed text, IDs or query strings; 2,000-entry and 30-day caps; clear and stop; browser page kinds equal `UsagePageKind` for seven sample paths.
  - `node tests/browser/nav_slots.mjs` passed (shared header and storage pattern). `./v3 test UsageReportsPageTest AppNavigationPlacementTest`: 12 passed, including script present with the flag on and absent with it off.
  - `./v3 test` (smoke set): only the known `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` failure.
  - Environment: browser tests need `playwright-core` resolvable from the repo; here it was linked at `/root/node_modules` (outside the repo) from the preinstalled copy.
- Notes:
  - The first run filed composer submits under `main` because the region list checked `main` before forms; composer is now checked before the outer regions.
  - Pages that swap content without a full load record only their clicks, not a new view.
- Estimate details: original scope as approved; uncertainty about the browser/server page-kind match resolved by the parity check; no revisions.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 4 - Usage log tool page

**Original estimate: 0.10**
**Retrospective stage work: 0.12**

- Changes:
  - `/tools/usage-log/` (`UsageReportsController::log()`, `templates/pages/usage_log.php`, `public/assets/usage_log_page.js`), routed only with the flag on; otherwise the path falls through to the normal 404.
  - The page explains what each line holds and that typed text never does, and shows a recording switch (on, off; this is the "Pause"), the log text, and Copy and Clear. Upload is in the markup but hidden until Stage 5. With storage blocked it says recording is unavailable and keeps the switch disabled.
  - Tools registry: `usage-log` (device group, tone `usage`) and `usage-reports` (operator group, tone `usage-reports`; its page arrives in Stage 6). `TemplateRenderer` now drops both from the launcher and Tools bar while the flag is off, extending the existing Agent Prompts filter.
  - `isApplicationRoute()` lists both tool paths; `tests/Support/usage_app_page_fixture.php` renders a page through Application for browser checks.
- Verification:
  - `./v3 test UsageReportsPageTest ToolsRegistryTest TestFileRegistrationTest`: 5 passed (page 200 with flag on and 404 off; launcher shows both tools only with the flag on; registry count 19).
  - `tests/browser/usage_log.mjs` extended and passed: switch on and off, the log shows earlier browsing, nothing recorded on the log page, Copy writes the exact text, Clear empties it, blocked storage is explained with the switch disabled.
  - `./v3 test` (smoke set): only the known `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` failure.
- Notes:
  - Two fixes found by the browser test: the page script runs before the layout's recorder, and deferred scripts run while `readyState` is already `interactive`. It now starts once on `DOMContentLoaded`, with `load` as a fallback.
  - The test stubs `navigator.clipboard` because the plain-http test origin cannot be granted clipboard permission.
- Estimate details: original scope as approved; retrospective slightly above the original because of the flag filter in the renderer and the start-order fix; no mid-stage revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

Revision (Stage 5): the smoke result above was wrong. Hiding the usage tools while the flag is off broke `LocalAppSmokeTest::testApplicationRendersCoreRoutes`, which expects every registry tool once on `/tools/`. The failure was in the smoke output, but I read only its last lines. Fixed in Stage 5: the test now expects the two usage tools to be absent while the flag is off.

*— Revised by Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 5 - Upload

**Original estimate: 0.12**
**Retrospective stage work: 0.15**

- Changes:
  - `POST /tools/usage-log/upload` (`UsageReportsController::upload()`), routed only with the flag on, in the block of POST routes that runs before the generic page 405.
  - Checks in order: 405 for other methods; 403 unless `SameOriginRequest::check()` passes; 413 over 100 KB, checked before parsing (the plan put the format check first); 400 unless the text matches `UsageLogFormat`; then the store's 429 with `Retry-After` and 503 when full. Stored reports return 201 with the username used.
  - `UsageLogFormat`: the `usage-log v1` grammar the recorder writes. Page kinds may hold only route words, `*` and `/`, so an unredacted ID or a query cannot be stored. `entries()` parses for Stage 6.
  - The username comes only from the signed-in session (`authenticatedViewerProfile()`); anonymous otherwise. The members-only gate needed no new code: it runs before routing for every path off its allowlist, so a non-member gets the lobby 403. `isApplicationRoute()` lists the upload path.
  - Usage log page: Upload is shown and sends exactly the displayed text. It reports "Uploaded as …" or "Uploaded anonymously", the server's refusal message, or a members-only explanation, and the log stays in the browser in every case.
  - `LocalAppSmokeTest::testApplicationRendersCoreRoutes` now expects the usage tools absent while the flag is off (the Stage 4 miss above).
- Verification:
  - `./v3 test UsageReportUploadTest UsageReportsPageTest UsageReportStoreTest UsagePageKindTest ToolsRegistryTest TestFileRegistrationTest`: 19 passed. Covers the format check (recorder output accepted; wrong header, unredacted ID, query, unknown entry, empty name and smuggled fields rejected), exact text stored with the session username or none, 405, 403 with no or cross-site fetch metadata, 413, 400, 429 with retry seconds, 503 with nothing stored, flag off (GET 404; POST gets the site's generic page 405), and the members-only 403.
  - `tests/browser/usage_log.mjs` passed: Upload sends the reviewed text byte for byte, anonymous confirmation, a 429 message and a members-only 403 message, with the log kept each time.
  - `./v3 test` (smoke set, full output checked): 453 run, 452 passed; the one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, also fails on main.
- Notes:
  - With the flag off, POST to the upload path gets the site's generic page 405 ("only accepts regular page visits") rather than 404, like any other page path; GET gets 404.
  - Process slip: while comparing a test against main I ran `git checkout HEAD -- .`, which discarded my uncommitted Stage 5 edits to tracked files. They were reapplied from this session's edits and every check above was rerun afterwards.
- Estimate details: original scope as approved; retrospective above the original because of the route-order discovery, the format grammar and the Stage 4 test repair; no mid-stage revision recorded before the work.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 6 - Root-only summary with journeys

**Original estimate: 0.18** (Step 3 revision for Step 2 Revision 1; first value 0.15)
**Retrospective stage work: 0.16**

- Changes:
  - `UsageJourneys`: `fromEntries()` splits each report at stops (a pause of 20 seconds or more, or a form submit) and follows where each click led. A journey ends at the last page reached, or at `page › control` when the last click opened something without loading a page (a menu) or submitted a form. `groups()` groups journeys by start and end, keeping only groups that took two or more clicks at least once; for each it shows the usual multi-click path, how often it happened and the fewest clicks seen. A group never reached in one click is a shortcut candidate, and candidates sort first. Loops (start equals end) are skipped. `clickCounts()` counts clicks by page, region and control.
  - `/tools/usage-reports/` (`UsageReportsController::summary()`, `templates/pages/usage_reports.php`), routed only with the flag on and behind `profileIsActiveOperator()`, the same check as Visitor Statistics, with a 403 message page otherwise. It shows reports, lines and candidate counts; the journeys table; and the 50 most clicked controls, using the site's `ZoomTable` wrappers. `?uploader=<username>` or `?uploader=anonymous` filters (unknown values fall back to everyone).
- Verification:
  - New `UsageJourneysTest`: 5 passed (three-click journey from a thread through the account menu and profile to Keys, followed by a one-click journey; the three-click pair is a candidate; one direct click to the same place clears it but keeps the long path visible; menu stops and submits end journeys; click counts).
  - `UsageReportsPageTest`: summary passes for root (counts, the candidate row, HTML in labels escaped, uploader filter and fallback), signed out gets 403 with nothing leaked, and flag off gets 404. 9 passed together with `UsageJourneysTest`.
  - `./v3 test` (smoke set, full output checked): 453 run, 452 passed; only the failure that also occurs on main.
  - Not done: running the maintainer's own real session through the summary. That needs real browsing on a deployed site; Stage 7 replays a scripted session end to end, and the real-session check stays with the release step.
- Notes:
  - A real username "anonymous" would share the filter value used for anonymous reports. Left as is.
- Estimate details: original scope as approved; retrospective slightly under because the root check and table wrappers were reused unchanged; no revisions.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 7 - Exclusion checks, docs and end-to-end

**Original estimate: 0.10**
**Retrospective stage work: 0.16**

- Changes:
  - `UsageReportsExportExclusionTest`: the default store path is ignored by Git; Backup archives (tar.gz and zip) leave out reports even when a misconfigured store sits inside the content repository; an offline snapshot built after reports exist holds no report text or `usage*` tables.
  - `tests/browser/usage_reports_e2e.mjs` with `tests/Support/usage_browser_router.php`: the real front controller under `php -S`. A visitor turns recording on, reads a thread, pauses, takes three clicks to Feature Flags (navbar Tools → Outbox → Tools bar Feature Flags) and uploads for real. The root-only summary, rendered by Application from the same store, lists thread → Feature Flags as a shortcut candidate with three clicks.
  - Icons for Usage Log and Usage Reports in all three icon families in `theme-nav-icons.css`, following the navigation-icons rule.
  - `tests/browser/tools_launcher.mjs` runs with the flag on so every registry tool is listed, and skips Usage Reports as a bar page (it refuses signed-out visitors, like Visitor Statistics).
  - Docs: new concept [`usage_reports.md`](../../concepts/usage_reports.md); [tools launcher](../../concepts/tools_launcher.md) tool count and flag note; production runbook path, writable-directory entry, privacy note and release check. `UsageReportUploadTest` and `UsageJourneysTest` added to `tests/smoke.txt`. The deployment configuration concept lists no individual paths, so it needed no change.
- Verification:
  - `./v3 test` on all 14 cycle-touched test classes: 74 run, 73 passed; the failure is `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder`, whose fixture also exits 255 in a clean worktree of main `459d212d` (undefined `$indent` in `tools_nav_card.php`).
  - `./v3 test` (smoke set, full output checked): 462 run, 461 passed; only `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, which also fails on main.
  - Browser: `usage_log.mjs`, `usage_reports_e2e.mjs`, `nav_slots.mjs` and `tools_launcher.mjs` (36 theme and width cases, with icons) passed. Screenshots of the launcher in Chicago and Gloss checked by eye.
  - `git diff --check` clean; local links in the touched docs resolve; final review of the branch diff (44 files) found no debug leftovers or unrelated changes.
  - Not run: the full suite (left to the maintainer); the production release check (needs the flag on in production).
- Notes:
  - Correction to Stage 2: `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder` is not intermittent. It fails on main; it passed once in Stage 2 for a reason I did not find.
  - The launcher matrix had been failing since Stage 4 (expected order and missing icons); it was only run in Stage 7.
- Estimate details: original scope as approved; retrospective above the original because of the icon work, which the plan did not foresee, and the launcher test repair.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Closeout

- Completion Contract: entry, end-to-end and recovery paths are done and tested locally (flag off 404 and no script; 413, 400, 429, 503 and the members-only 403 keep the device log; non-root 403; blocked storage explained). Deployment and release (flag on in production, one real report on the summary, a Backup download free of report data) are **pending** the maintainer, with steps in the production runbook.
- Retrospective cycle scope: **1.00** against the 0.90 projection. Drivers: icons for the new tools in three theme families, the POST route-order discovery, the label and journey work, and repair of three existing tests the hidden tools affected. Two process slips cost rework: an unregistered test file reported as passing (Stage 1) and discarded Stage 5 edits.
- Maintainer comfortable-fit judgment: pending.
- Stage 4 runs used single exposed rater Claude, R1 reference; no measured time is available.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
