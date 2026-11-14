> **Feature plan:** [Step 1](./local_telemetry_step1_solution_assessment.md) · [Step 2](./local_telemetry_step2_feature_description.md) · [Step 3](./local_telemetry_step3_development_plan.md) · [Step 4](./local_telemetry_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_telemetry`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.90** (Step 2 Revision 1; was 0.85)

## Completion Contract

- **Entry:** with the "Usage reports" flag on, a visitor opens Tools → Your device → Usage log; a root operator opens Tools → Operator → Usage reports.
- **End-to-end:** the visitor turns recording on, browses, reads the log and presses Upload. The report is stored with their session username or as anonymous, and the operator's summary shows its click counts and journeys, with multi-click journeys that have no one-click equal marked as shortcut candidates.
- **Recovery:** 413 for an oversized report, 429 with Retry-After at the client limit, and 503 when the store is full, each keeping the device log intact and saying what to do. Non-root viewers get 403 on the summary. With storage blocked, the log page says recording is unavailable. With the flag off, both tools and the upload route are absent (404).
- **Deployment:** the flag ships off. On production: deploy, turn the flag on, upload one real report, see it on the summary, then download a Backup archive and confirm it holds no report data.
- **Release:** every stage passes, the production check passes, and the PR is merged.

## Key Risks

- **High risk:** a page kind leaks IDs or names. Impact: reports carry thread IDs or usernames that the visitor did not expect. Early validation: Stage 2's test runs every route pattern through the redaction. Mitigation: an allowlist (keep the first path segment and known literal words; replace everything else with `*`), plus the visitor review screen.
- **High risk:** junk or flood uploads. Impact: store growth and noisy summaries. Early validation: Stage 5's tests with oversized, repeated and full-store uploads. Mitigation: 100 KB per report, 10 uploads per client per hour, a 50 MB store ceiling, the flag, and a strict report format check.
- Journey boundaries mislead. Impact: wasted redesign effort. Early validation: run the maintainer's own session through Stage 6. Mitigation: one stated rule (20-second stop or form submit), shown on the page; candidates labeled as hints.

## Stage 1

**Estimate: 0.10**

- Goal: a private report store, its config path and the feature flag.
- Dependencies: none.
- Expected changes: `UsageReportStore` over `state/private/usage_reports.sqlite3` with `add(string $body, ?string $username, string $clientHash, int $now): UsageReportResult`, `retryAfter(string $clientHash, int $now): int`, `totalBytes(): int` and `reports(?string $username): iterable`. Conceptually two tables: reports (received time, username or none, body, size) and upload windows (hashed client, window start, count; windows older than an hour are pruned). Add `UsageReportDatabaseConfig`, a `USAGE_REPORTS_DATABASE_PATH` private-config entry, and a `USAGE_REPORTS_ENABLED` flag (off by default).
- Verification approach: new store and config tests; extend `PrivateConfigSchemaTest` and the flag registry test.
- Estimate details: driver: copying the visitor-statistics store pattern; uncertainty: low.
- Risks or open questions: the store must hold hashed client keys only. Impact: raw IPs stored. Early warning / validation: the store test checks that columns hold no raw IP. Mitigation: hash IP and user agent with SHA-256 before the store sees them.
- Canonical components/API contracts touched: [`VisitorStatisticsDatabaseConfig.php`](../../../src/ForumRewrite/Statistics/VisitorStatisticsDatabaseConfig.php) and `VisitorStatisticsStore.php` (pattern copied), [`PrivateConfigSchema.php`](../../../src/ForumRewrite/Support/PrivateConfigSchema.php) (extended), [`FeatureFlagRegistry.php`](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php) (one entry).

## Stage 2

**Estimate: 0.08**

- Goal: each page carries a redacted page kind.
- Dependencies: Stage 1 (flag).
- Expected changes: `UsagePageKind::fromPath(string $path): string`; when the flag is on, `layout.php` adds `data-usage-page` to `<html>`, next to the existing `data-page-path`, and publishes the route-pattern table for the browser (Stage 3).
- Verification approach: a unit test over every route pattern in `Application.php` (threads, profiles, tags, tools, messages) asserting no ID, username, tag or query survives.
- Estimate details: driver: enumerating routes; uncertainty: routes without a shared table may be missed.
- Risks or open questions: see the first key risk.
- Canonical components/API contracts touched: [`layout.php`](../../../templates/layout.php) (one attribute).

## Stage 3

**Estimate: 0.15** (was 0.12; Revision 1)

- Goal: the browser records page views and every link and button click while recording is on.
- Dependencies: Stage 2.
- Expected changes: `public/assets/usage_log.js`, loaded only when the flag is on. It keeps `forum_usage_log` (recording state and entries) in localStorage with the same guarded read and write as `nav_slots.js`. One entry per page view (page kind, seconds since the last entry) and per click on any `a` or `button`, including menu toggles. A click entry holds the page kind; the region, from the nearest landmark (`nav`, header menu, Tools bar, thread list, post, composer, footer); the name, from `data-tab`, `data-tone` or `data-action`, else `aria-label`, `title` or visible text cut to 40 characters; and for same-site links, the destination's page kind. The browser computes the destination's page kind with a small table of route patterns that the server publishes from Stage 2's helper, so both use the same rules. Form submits are recorded as entries too, because they end journeys. Capped at 2,000 entries and 30 days.
- Verification approach: a new `tests/browser/usage_log.mjs`: nothing recorded while off; with recording on, a navbar click, a menu toggle, an unhooked link (label fallback) and a page view are recorded with the right region and destination; caps trim old entries; no typed input is captured.
- Estimate details: driver: browser test setup and label and region rules; uncertainty: pages that swap content without a full load, and the browser-side route table matching the server's.
- Risks or open questions:
  - Icon-only controls get poor names. Impact: unreadable journeys. Early warning / validation: list the names a sample session yields. Mitigation: `aria-label`/`title` fallback, then region plus element type.
  - The browser and server compute different page kinds. Impact: journeys don't line up. Early warning / validation: the Stage 2 test also runs the published table through the browser code. Mitigation: one source table.
- Canonical components/API contracts touched: [`nav_slots.js`](../../../public/assets/nav_slots.js) (storage pattern reused); `data-tab` in [`app_header.php`](../../../templates/partials/app_header.php), `data-tone` and `data-action` (read only).

## Stage 4

**Estimate: 0.10**

- Goal: the Usage log tool page.
- Dependencies: Stage 3.
- Expected changes: a registry entry `usage-log` (device group) shown only with the flag on; `/tools/usage-log/` with an on/off switch, a plain-language list of what is recorded, the log as text, and Pause, Clear, Copy and Upload (wired in Stage 5). With storage blocked, it says recording is unavailable.
- Verification approach: a page test (flag on and off); update `ToolsRegistryTest` for the new count; extend the browser test for the switch, Copy and Clear.
- Estimate details: driver: page and template; uncertainty: low.
- Risks or open questions: none beyond the key risks.
- Canonical components/API contracts touched: [`ToolsPageSupport.php`](../../../src/ForumRewrite/Tools/ToolsPageSupport.php) `registry()` (extended), [`tools_nav.php`](../../../templates/partials/tools_nav.php) (used).

## Stage 5

**Estimate: 0.12**

- Goal: upload.
- Dependencies: Stages 1 and 4.
- Expected changes: `POST /tools/usage-log/upload` sending the reviewed text exactly. The server checks the format (one entry per line in the log's own grammar), then the size cap (413), then the client limit (429 with Retry-After), then the store ceiling (503). It takes the username from the [viewer session](../../concepts/viewer_session.md), or none. The page shows the result and leaves the device log unchanged.
- Verification approach: a controller test for success with and without a session, each refusal, a forged username in the body being ignored, flag off returning 404, and the members-only gate applying.
- Estimate details: driver: refusal cases; uncertainty: the members-only gate must cover this route the way it covers anonymous posting.
- Risks or open questions: see the second key risk.
- Canonical components/API contracts touched: [`PrivateMessageHistorySyncController.php`](../../../src/ForumRewrite/Http/PrivateMessageHistorySyncController.php) (429 response pattern copied), and the client key as built in [`VisitorStatisticsObserver.php`](../../../src/ForumRewrite/Statistics/VisitorStatisticsObserver.php) (hashed).

## Stage 6

**Estimate: 0.18** (was 0.15; Revision 1)

- Goal: a root-only Usage reports summary with journeys and shortcut candidates.
- Dependencies: Stage 5.
- Expected changes: registry entry `usage-reports` (operator group); `/tools/usage-reports/` behind `profileIsActiveOperator()` with a 403 otherwise. It shows a report count, click counts per page kind and control, and a journeys table. `UsageJourneys::fromEntries(array $entries): array` splits each report at stops (20 seconds or more on a page or open menu, or a form submit). The table groups journeys of two or more clicks by start and destination, shows the common click sequence, count and fewest clicks seen, and marks a pair never reached in one click as a shortcut candidate. It filters by uploader (all, a username or anonymous) using readable query parameters.
- Verification approach: a `UsageJourneysTest` (stop splitting, grouping, shortcut marking, one-click pair not marked); a page test with seeded reports (counts, journeys, filter, 403); run the maintainer's own session.
- Estimate details: driver: journey splitting and grouping; uncertainty: whether the 20-second stop reads well on real data.
- Risks or open questions: see the third key risk.
- Canonical components/API contracts touched: [`VisitorStatisticsController.php`](../../../src/ForumRewrite/Http/VisitorStatisticsController.php) and `viewerCanInspectVisitorStatistics()` in `Application.php` (root-check pattern reused).

## Stage 7

**Estimate: 0.10**

- Goal: exclusion checks, docs and the end-to-end check.
- Dependencies: Stages 1–6.
- Expected changes: tests that a Backup archive and an offline snapshot built after an upload contain no report data; the browser test extended through the full flow, including a scripted three-click route that appears as a shortcut candidate; new concept [`usage_reports.md`](../../concepts/usage_reports.md); updates to [tools launcher](../../concepts/tools_launcher.md) (tool count), [deployment configuration](../../concepts/deployment_configuration.md) (path) and the production runbook (flag and check); new tests added to `tests/smoke.txt`.
- Verification approach: the smoke set plus this cycle's tests (full suite left to the maintainer); `git diff --check`; a final diff review for duplication and scope; the production check from the Contract.
- Estimate details: driver: export tests and docs; uncertainty: low.
- Risks or open questions: the production check needs the maintainer to turn the flag on. Impact: release stays pending until then. Early warning / validation: say so in the handoff. Mitigation: give exact steps.
- Canonical components/API contracts touched: [content snapshot export](../../concepts/content_snapshot_export.md) (used unchanged, now tested).

## Concepts touched

- *Usage reports*: created in Stage 7.
- Tools launcher and deployment configuration: changed.
- Navigation slots, viewer session and content snapshot export: used unchanged.

## Study Checkpoint

2026-11-11T06:30:00Z; Step 3 handoff; scope v3/B, unchanged from Approved Step 2; starting revision `6d0f9da1`; R1/P2, single exposed Claude rater. Projected total 0.75 → **0.85** from new understanding, not changed scope: page kinds need a server-side redaction helper (the existing `data-page-path` holds raw IDs), and the recorder needs its own browser test. Stage estimates are supplemental and non-additive.

2026-11-11T22:20:00Z revision for Step 2 Revision 1 (every click, journeys and shortcut candidates): scope v4/B; projected total 0.85 → **0.90** (changed scope). Stage 3 0.12 → 0.15, Stage 6 0.15 → 0.18, Stage 2 grows by a published route table within 0.08; others unchanged. Same R1/P2, single exposed Claude rater. Not hours or measured effort; no approval to advance.

Status: **Approved Step 3** (maintainer, 2026-11-14T19:08Z). Planning records uncommitted.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

*— Revised by Claude (Stages 2, 3, 6, 7) · 2026-11-11 UTC · Model: claude-opus-5-5*
