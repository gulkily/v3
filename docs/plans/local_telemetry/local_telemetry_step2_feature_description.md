> **Feature plan:** [Step 1](./local_telemetry_step1_solution_assessment.md) · [Step 2](./local_telemetry_step2_feature_description.md) · [Step 3](./local_telemetry_step3_development_plan.md) · [Step 4](./local_telemetry_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_telemetry`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.90** (Revision 1; was 0.75)

## Revised Direction

Revision 1 (maintainer, 2026-11-11, after Approved Step 2): "I will be the primary test subject, so we don't have to worry about privacy too much. I just want to analyze where I follow a path of like 3 clicks when it could be 1 click."

The main question becomes multi-click journeys that could be one click. That needs every click, including menu openings that load no page, so the recorder now logs all link and button clicks, not only controls with existing hooks. Page kinds stay redacted because a journey compares kinds of pages, not single threads, and other visitors may still upload. This changes approved requirements, so Step 2 needs approval again.

## Problem

The operator has no evidence of which controls people use or where they take long or looping routes, so interface changes such as the navbar rethink rest on guesses. Any evidence must start on the visitor's own device and reach the server only when the visitor chooses to send it.

## User stories

- As a visitor, I want to turn on a usage log that stays in my browser so that I can help improve the site without being tracked by default.
- As a visitor, I want to see exactly what the log holds, and copy, clear or pause it, so that I decide what leaves my device.
- As a visitor, signed in or not, I want to upload my log with one button so that the operator receives it without me writing a message.
- As a root operator, I want one page summarizing all received reports so that I can see the busiest controls and the routes worth shortening.
- As the operator testing on myself, I want to see journeys where I took several clicks to reach a place so that I can add one-click shortcuts.

## Core requirements

- Recording is off until the visitor turns it on in Tools → Your device → Usage log, and the choice is per browser. Each page view records the page kind (route pattern with IDs removed) and the seconds since the previous entry. Each click on any link or button records the page kind, the page region (navbar, menu, Tools bar, thread list, post, composer, footer), the control's name (its existing hook, else its visible label cut to 40 characters), and for links the destination's page kind. No typed input, query strings or IDs.
- The visitor sees the full log as plain text before any upload. Uploading sends that exact text, nothing added on the device.
- Anyone may upload. The server attaches the uploader's username from their signed-in session, or marks the report anonymous; it never trusts a name inside the report. On a members-only site, upload follows the same gate as anonymous posting.
- Reports are kept indefinitely in one private SQLite store under `state/private/` (path overridable in private config). The store is outside the content repository, Backup archives, the offline snapshot and message data.
- Only root operators can open the summary page, over all reports or one uploader. It shows click counts per page kind and control, and a journeys table. A journey is the run of clicks from one stop to the next; a stop is a page or open menu where the visitor stays 20 seconds or more, or submits a form. The table lists the most common journeys of two or more clicks (start, clicks taken, destination), with the fewest clicks ever seen between that start and destination. A journey that never happened in one click is marked as a shortcut candidate.

## Delivery scope

Application change. Settled caps: at most 2,000 entries or 30 days kept on the device; 100 KB per report; a per-client upload limit answered with 429 and Retry-After; a total store ceiling past which new uploads are refused and nothing is deleted. Exact client limit and ceiling are set in Step 3. The whole feature sits behind one feature flag, off by default. Not in this cycle: senders withdrawing a report, operator deletion from the page, exporting reports, and adding new hooks to templates (labels cover unhooked controls).

## Completion boundary

- **Entry:** Tools → Your device → Usage log (visitor); Tools → Operator → Usage reports (root).
- **End-to-end:** a visitor turns recording on, browses, reviews the log, uploads; a root operator sees that report in the counts, and a three-click journey with no one-click equal appears as a shortcut candidate.
- **Recovery:** a rate-limited or full-store upload keeps the log on the device and says when to retry; a non-root viewer of the summary gets 403 as on Visitor Statistics; with storage blocked the page says recording is unavailable.
- **Release:** flag on in production, one real report seen on the summary, and a fresh Backup archive confirmed free of report data.

## Risks

- **Abuse of open uploads** (store growth, junk reports). Validate early with oversized and repeated uploads in tests; mitigate with the three caps and the flag.
- **Reports reveal more than intended.** If a route pattern slips through unredacted, thread IDs leak. Validate with a redaction test over every page kind; the visitor review screen is the last check.
- **Unclear labels.** Icon-only buttons have no visible text. Validate by listing the names a sample session yields; fall back to `aria-label` or `title`, then to region and element type.
- **Labels carry content.** A link's visible text inside a post or thread list may be a thread title. Accepted for now (Revision 1); the visitor still sees the text before upload.
- **Journey boundaries mislead.** A 20-second stop may split or merge real journeys. Validate against the maintainer's own session; show the threshold on the page and treat candidates as hints.

## Shared component inventory

- Tools registry and bar: [`ToolsPageSupport.php`](../../../src/ForumRewrite/Tools/ToolsPageSupport.php) `registry()`; extend with Usage log (device group) and Usage reports (operator group).
- Root-only page pattern: [`VisitorStatisticsController.php`](../../../src/ForumRewrite/Http/VisitorStatisticsController.php) (`viewerCanInspect`, 403 copy); reuse for the summary.
- Browser-only storage: [`nav_slots.js`](../../../public/assets/nav_slots.js) already counts navigation use in localStorage with guarded reads and writes; reuse its storage approach and navbar keys.
- Control names: existing `data-tab` (navbar, [`app_header.php`](../../../templates/partials/app_header.php)), `data-tone` (Tools) and `data-action` (about 66 buttons); reuse, no template changes.
- Upload limiting: history sync's `admit()` and 429 response in [`PrivateMessageHistorySyncController.php`](../../../src/ForumRewrite/Http/PrivateMessageHistorySyncController.php); copy the pattern, keyed per client instead of per account.
- Private store and config: the `*DatabaseConfig.php` classes (e.g. [`VisitorStatisticsDatabaseConfig.php`](../../../src/ForumRewrite/Statistics/VisitorStatisticsDatabaseConfig.php)) and [`PrivateConfigSchema.php`](../../../src/ForumRewrite/Support/PrivateConfigSchema.php); follow both.
- Flag: [`FeatureFlagRegistry.php`](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php); add one entry.
- Session username: [viewer session](../../concepts/viewer_session.md); used unchanged.

## Concepts touched

- Proposed *usage report* (created): the opt-in on-device log, its upload and the private report store; new inventory entry `docs/concepts/usage_reports.md` at implementation.
- [Tools launcher](../../concepts/tools_launcher.md) (changed: two tools, so the count rises from 16).
- [Navigation slots](../../concepts/navigation_slots.md), [viewer session](../../concepts/viewer_session.md), [content snapshot export](../../concepts/content_snapshot_export.md) (used unchanged; the export check confirms exclusion).
- [Deployment configuration](../../concepts/deployment_configuration.md) (changed: one new private path setting).

## User flow

1. A visitor opens Tools → Usage log and turns recording on.
2. They browse; each page view and named control use adds a line on the device.
3. They return to Usage log, read the text, and press Upload (or Copy).
4. The server stores the report with their session username or as anonymous and confirms receipt.
5. A root operator opens Tools → Usage reports and reads control counts and flagged routes.

## Success criteria

- With recording off, no entries are written and no upload request is possible.
- An uploaded report matches the reviewed text byte for byte and carries the session username, or "anonymous".
- A redaction test across every page kind finds no IDs or query strings in page kinds or link destinations.
- A scripted three-click route between two pages with no one-click equal appears as a shortcut candidate; the same route taken once in one click does not.
- Oversized, rate-limited and full-store uploads are refused with the documented responses, and the device log survives each.
- Non-root viewers get 403 on the summary; a Backup archive and offline snapshot built after uploads contain no report data.

## Enhancement Requests

None pending.

## Study Checkpoint

2026-11-10T17:26:43Z; Step 2 handoff; scope v3/B (caps, flag and exclusions settled above); starting revision moved to main `b50af911` after sync; R1/P2, single exposed Claude rater. Projected total **0.75 reaffirmed**: the flag and caps add small surface; reusing nav-slot storage, the root-check closure and the admission pattern offsets it. Not hours or measured effort; no approval to advance.

2026-11-11T22:20:00Z Revision 1: every link and button click recorded with region, name and destination; journeys table with shortcut candidates replaces the bounce and search flags. Scope v4/B; projected total 0.85 (Step 3) → **0.90** (changed scope). Same R1/P2, single exposed Claude rater.

Status: **Approved Step 2** with Revision 1 (maintainer's "approved step 3", 2026-11-14T19:08Z, read as approving the revised Step 2 the plan builds on). Planning records uncommitted.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
