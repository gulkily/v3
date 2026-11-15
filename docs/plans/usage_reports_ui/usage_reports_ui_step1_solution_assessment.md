> **Feature plan:** [Step 1](./usage_reports_ui_step1_solution_assessment.md) · [Step 2](./usage_reports_ui_step2_feature_description.md) · [Step 3](./usage_reports_ui_step3_development_plan.md) · [Step 4](./usage_reports_ui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/usage_reports_ui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.45

## Original Query

"Can you please make a new step 1 for improving the UI of this feature?" (the usage reports feature from [local_telemetry](../local_telemetry/local_telemetry_step4_implementation_summary.md), after the upload fix in PR #238).

## Understood Intent

Make Tools → Usage Log and Tools → Usage Reports pleasant and quick to read, so that finding a "three clicks that could be one" path takes a glance, not decoding. The recording, upload and journey rules stay as they are; this is presentation. The request names no specific problem, so the scope below comes from screenshots of both pages at 1280 px and 390 px with seeded reports (main `bd2b0fad`).

What the screenshots show:

- **Summary page unstyled in places.** It reuses Visitor Statistics class names (`visitor-statistics-tiles`, `visitor-statistics-period`) but `TemplateRenderer` never loads `visitor_statistics.css` for `usage_reports.php`, so the three numbers render as a bare definition list and the uploader filter as a browser-default select.
- **Machine names instead of words.** Journeys read `/tools/user-access/profiles/*` and `tab:tools → tool:usage → tool:flags → submit Save`. The operator has to translate page kinds and hook names (`tab:`, `tool:`, `action:`) back into what they saw on screen.
- **Journeys table fails on a phone.** At 390 px the six-column table zooms down to unreadable text; most operator checks happen on a phone.
- **Usage Log shows raw log text.** The textarea shows tab-separated `view 0 /threads/*` lines. It is exact, which matters before upload, but it does not tell the visitor what they did. Upload, Copy and Clear look the same weight, and recording state is a sentence plus a small checkbox.

## Problem Statement

Both pages show the data as stored rather than as the person reading them would describe it, and the summary is partly unstyled and unreadable on a phone.

## Concepts touched

- All options: [usage reports](../../concepts/usage_reports.md) (changed: how logs and journeys are shown; recording, storage and journey rules unchanged).
- Options A and B: proposed *readable page name* (a short name for a page kind, such as "Thread" for `/threads/*`), shared by both pages.
- Option B: proposed *journey detail* view, using readable filter parameters like the existing `?uploader=`.

## Solution Options

### Option A: Readable labels, phone-first journeys, styled pages

**Estimate: 0.45**

- Summary: load its stylesheet (fixing the tiles and filter); show journeys as a stacked list of cards, each reading like a sentence ("Thread → Tools → Usage Log → Feature Flags, Save · 3 times · never in one click"), with the shortcut candidates first and visibly marked; keep "Most clicked" as a table with readable names.
- Shared readable names: page kinds map to short names ("Thread", "Profile", "Keys", "Board"; unknown kinds fall back to the pattern), and hook names drop their prefixes (`tab:tools` → "Tools", `tool:flags` → "Feature Flags", `action:reply` → "Reply"). The raw form stays available as a tooltip or secondary text.
- Usage Log: a clear on/off status at the top; the log shown as a short readable list of the latest steps ("Clicked Tools in the navbar on Board"), with the exact upload text behind a "Show exact text" disclosure; Upload as the primary button, Copy and Clear secondary.

- Pros: answers the actual question on any screen; no change to stored data or rules; small surface (templates, one stylesheet entry, one naming helper, page script).
- Cons: the name map needs upkeep as routes are added (unknown kinds fall back, so nothing breaks); the Usage Log list is a second view of the same data to keep in step.
- Driver: the name map and journey cards. Uncertainty: how many page kinds need names to read well (about 20 cover common browsing).

### Option B: Option A plus a journey detail view

**Estimate: 0.65**

Everything in A, and each journey card links to a detail page (`/tools/usage-reports/?from=thread&to=feature-flags`) listing every path taken between that start and end, how often, and which uploader took it, so the operator can see the competing routes before deciding where a shortcut goes.

- Pros: shows why a path is long, not just that it is; useful once there are many reports.
- Cons: a new view and query for what is today a handful of reports from mostly one person; more to test.
- Driver: the detail view and its filter. Uncertainty: whether the extra view earns its place before reports accumulate.

### Option C: Styling fixes only

**Estimate: 0.15**

Load the missing stylesheet, make the journeys table usable at phone width, and give Upload primary weight. No naming changes.

- Pros: smallest change; fixes the visibly broken parts.
- Cons: the operator still decodes `tab:tools → tool:usage` and page patterns; the main reading cost stays.
- Driver: CSS and template markup. Uncertainty: low.

## Recommendation

**Option A.** The main cost today is translating machine names, and the phone layout; A fixes both without touching stored data. B's detail view can follow if reports pile up and a single "usual path" stops being enough.

**Vertical-slice viability:** with the three seeded reports used for the screenshots, the summary at 390 px shows three journey cards starting with "Thread → account menu → Profile → Keys · 3 times · never in one click", styled tiles and filter, and readable names in Most clicked. The Usage Log page shows "Recording is on", the latest steps in words, and the exact text behind a disclosure that still matches the upload byte for byte. Step 2 should settle which page kinds get names in the first slice and how much of the log the readable list shows.

## Enhancement Requests

None.

## Study Checkpoint

2026-11-15T02:17:36Z; scope v1; starting revision `bd2b0fad`; reference R1 (hashes not re-verified this session); P2 standing enrollment, no parent. Claude, single exposed rater; pristine intake missing because both pages were rendered, screenshotted and inspected before rating.

- Intake after inspection and Step 1 handoff: **0.45 projected total**, Option A. Alternatives B 0.65 and C 0.15, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: **Approved Step 1** (maintainer, 2026-11-15T04:54Z), after reviewing the Option A mock-up. Committed on branch `plan/usage_reports_ui` at the maintainer's request (ahead of the usual planning commit after Step 3).

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
