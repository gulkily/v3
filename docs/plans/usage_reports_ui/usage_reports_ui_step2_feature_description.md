> **Feature plan:** [Step 1](./usage_reports_ui_step1_solution_assessment.md) · [Step 2](./usage_reports_ui_step2_feature_description.md) · [Step 3](./usage_reports_ui_step3_development_plan.md) · [Step 4](./usage_reports_ui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/usage_reports_ui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

## Problem

Tools → Usage Log and Tools → Usage Reports show data as it is stored (page patterns, hook names, tab-separated lines), the summary is partly unstyled, and its journeys table is unreadable on a phone. Spotting a "three clicks that could be one" path takes decoding instead of a glance.

## User stories

- As the operator on my phone, I want shortcut ideas as readable cards ("Thread → Keys: You menu › Profile › Keys") so that I can see at a glance where to add a one-click path.
- As the operator, I want pages and controls named as I see them on screen ("You menu on Thread"), with the raw form available when I need it, so that I don't translate `/threads/*` and `tab:account`.
- As a visitor recording, I want to see clearly that recording is on and what I did in plain sentences, so that I know what Upload will send before I press it.
- As a visitor about to upload, I want the exact upload text one tap away, so that I can still check it byte for byte.

## Core requirements

- **Readable names, one source.** A single table maps page kinds to short names (`/threads/*` → "Thread", `/` → "Board", `/tools/user-access/profiles/*` → "Keys") and hook names to labels (`tab:tools` → "Tools", `tool:flags` → "Feature Flags", `action:reply` → "Reply"); an unknown kind or hook falls back to the raw form. The server uses it for the summary and publishes it on the Usage Log page for the browser, so both pages use the same words.
- **Raw form kept.** Every readable name carries its raw form as a `title` (hover) and in the accessible description; nothing stored changes.
- **Usage Reports:** loads its stylesheet; the uploader filter becomes a row of links (Everyone · each uploader with a count · Anonymous) using the existing `?uploader=` URLs; three styled tiles (Reports, Steps, Shortcut ideas); journeys become cards, shortcut ideas first, each with a "From → To" heading, the usual clicks as a row of steps, click count and times; Most clicked becomes a ranked list with bars. At 390 px everything stacks in one column with no zoomed table; at desktop width journeys and Most clicked sit side by side.
- **Usage Log:** a status card at the top ("Recording" or "Not recording", steps saved, the switch beside it); the latest steps as sentences ("Clicked Tools in the navbar"), with "paused N s" dividers where a journey stop falls; "Show all N steps" for the rest; the exact upload text inside a closed "Show exact text (what Upload sends)" disclosure; Upload as the primary button naming the step count, Copy and Clear secondary; a line saying who the upload is attributed to.
- **Unchanged:** recording rules, log format, upload, storage, journey and stop rules, access (root-only summary), the flag, and the exact bytes Upload sends.

## Decisions to confirm (from the mock-up)

- "Shortcut candidates" is renamed **"Shortcut ideas"** on the page.
- The uploader dropdown becomes **links shown as pills**; each is a plain URL, so it works without JavaScript and needs no submit button.
- Raw names appear **on hover or long-press** (`title`), not beside the readable name.

## Delivery scope

Application change, presentation only. In scope: the two templates, their page script and stylesheets, the names table, and controller data shaping. Not in scope: Option B's journey detail view, new data, renaming routes or the flag, and naming every page kind (unknown kinds fall back to the raw form).

## Completion boundary

- **Entry:** unchanged: Tools → Usage Log; Tools → Usage Reports for root operators.
- **End-to-end:** with seeded reports, the phone summary shows styled tiles, uploader pills, readable shortcut-idea cards first and Most clicked as a list; the Usage Log shows the status card, readable latest steps and the exact text behind the disclosure, and Upload still sends exactly that text.
- **Recovery:** unchanged refusal messages; with storage blocked the status card says recording is unavailable; an unknown page kind or hook shows in raw form, not blank.
- **Release:** deployed with the flag on, the maintainer opens both pages on his phone and finds a shortcut idea readable without decoding.

## Risks

- **The names table drifts** as routes are added. Validate with a test that every route word in `UsagePageKind::WORDS` either has a name or is listed as raw-by-design; the fallback keeps pages correct meanwhile.
- **Readable list and exact text disagree.** Both are built from the same entries; a test renders a log both ways and checks step for step.
- **Card layout grows long** with many journeys. Keep the existing 50-row cap and show shortcut ideas first.

## Shared component inventory

- Page stylesheets: `TemplateRenderer` per-template map (`usage_log.php` → `tools.css` today); add `usage_reports.php` and reuse `visitor_statistics.css` where its classes fit, new rules in `tools.css`.
- Data: `UsageJourneys::groups()`/`clickCounts()` and `UsageLogFormat::entries()`; used unchanged, names applied in `UsageReportsController::summary()`.
- Page kinds: `UsagePageKind::WORDS` (unchanged; the names table keys off its kinds).
- Recorder: `public/assets/usage_log.js` `window.forumUsageLog` (`text()`, `state()`); extend with read access to entries for the readable list, no change to recording.
- Templates: `templates/pages/usage_log.php`, `templates/pages/usage_reports.php`, `public/assets/usage_log_page.js`.
- Visual reference: the "Usage Reports UI mock-up" design canvas (Usage Log phone, Usage Reports phone and desktop).

## Concepts touched

- [Usage reports](../../concepts/usage_reports.md) (changed: presentation and the names table).
- Proposed *readable page name* (created): the names table, documented in the usage reports concept rather than its own file.

## User flow

1. A visitor opens Tools → Usage Log, sees "Not recording", and switches it on; the card turns to "Recording".
2. They browse; back on Usage Log they read their latest steps as sentences, open "Show exact text" if they want, and press "Upload N steps".
3. The operator opens Tools → Usage Reports on a phone, taps their own name pill, and reads the shortcut ideas first.

## Success criteria

- Both pages at 390 px: no horizontal scroll, no zoomed table, every control at least 44 px tall.
- Summary tiles and filter render with styles (no browser-default definition list or select).
- Every route word has a readable name or a raw-by-design entry (test).
- Upload sends exactly the text inside the disclosure (existing browser test still passes).
- The readable step list and the exact text describe the same entries (test).

## Enhancement Requests

None pending.

## Study Checkpoint

2026-11-15T04:54:33Z; Step 2 handoff; scope v1/A plus the mock-up's three presentation decisions; starting revision `bd2b0fad`; R1/P2, single exposed Claude rater. Projected total **0.45 reaffirmed**: the shared names table published to the browser replaces a server-only map, offset by reusing the existing stylesheet and data unchanged. Not hours or measured effort; no approval to advance.

Status: **Approved Step 2** (maintainer, 2026-11-15T05:15Z), including the three decisions above.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
