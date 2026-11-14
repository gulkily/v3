> **Feature plan:** [Step 1](./local_telemetry_step1_solution_assessment.md) · [Step 2](./local_telemetry_step2_feature_description.md) · [Step 3](./local_telemetry_step3_development_plan.md) · [Step 4](./local_telemetry_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; [debug log](./local_telemetry_debug_log.md))

Effort study: `railspike-effort` / `v3/local_telemetry`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.90** (Step 4 entry, reaffirmed from Step 3)

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

- Status: pending.

## Stage 5 - Upload

**Original estimate: 0.12**

- Status: pending.

## Stage 6 - Root-only summary with journeys

**Original estimate: 0.18** (Step 3 revision for Step 2 Revision 1; first value 0.15)

- Status: pending.

## Stage 7 - Exclusion checks, docs and end-to-end

**Original estimate: 0.10**

- Status: pending.
