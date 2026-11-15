> **Feature plan:** [Step 1](./usage_reports_ui_step1_solution_assessment.md) · [Step 2](./usage_reports_ui_step2_feature_description.md) · [Step 3](./usage_reports_ui_step3_development_plan.md) · [Step 4](./usage_reports_ui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/usage_reports_ui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

## Completion Contract

- **Entry:** unchanged: Tools → Usage Log (any visitor, flag on); Tools → Usage Reports (root operators).
- **End-to-end:** with seeded reports at 390 px, Usage Reports shows styled tiles, uploader pills, "Shortcut ideas" cards first with readable steps, and Most clicked as a ranked list. Usage Log shows the status card, latest steps as sentences with pause dividers, and the exact text behind a closed disclosure; Upload still sends exactly that text.
- **Recovery:** refusal messages unchanged; blocked storage shows "Recording is unavailable" in the status card; unknown page kinds and controls show in raw form.
- **Deployment:** no config or flag change; deploy as usual.
- **Release:** every stage passes; the maintainer opens both pages on his phone with the flag on and reads a shortcut idea without decoding; the PR is merged.

## Key Risks

- **High risk:** Upload stops matching the shown text after the page is rebuilt. Impact: visitors upload something other than what they reviewed. Early validation: the existing `usage_log.mjs` assertion that the upload's `log` equals the exact text, run in Stage 3. Mitigation: the disclosure and Upload both read `forumUsageLog.text()`; the readable list is a separate view of the same entries.
- The names table drifts from the routes. Impact: raw names creep back. Early validation: Stage 1's coverage test. Mitigation: fallback to the raw form.

## Stage 1

**Estimate: 0.08**

- Goal: one readable-names table, usable by the server and the browser.
- Dependencies: none.
- Expected changes: `UsageNames` in `src/ForumRewrite/UsageReports/` with `PAGES` (page kind → name, e.g. `/` → "Board", `/threads/*` → "Thread", `/tools/user-access/profiles/*` → "Keys"), `CONTROLS` (hook value → label, e.g. `tab:account` → "You menu", `tool:flags` → "Feature Flags"), `RAW_BY_DESIGN` (kinds deliberately left raw), `page(string $kind): string`, `control(string $name): string` (strips `tab:`/`tool:`/`action:` and title-cases when not listed; free labels pass through) and `forBrowser(): array`.
- Verification approach: new `UsageNamesTest`: known names; unknown kind and hook fall back; every page kind produced by the route samples in `UsagePageKindTest` has a name or is raw-by-design.
- Estimate details: driver: choosing about 20 names; uncertainty: low.
- Risks or open questions: hook values are not centrally listed; the fallback covers unlisted ones.
- Canonical components/API contracts touched: `UsagePageKind::WORDS` and `fromPath()` (read, unchanged).

## Stage 2

**Estimate: 0.15**

- Goal: the readable, phone-first Usage Reports page.
- Dependencies: Stage 1.
- Expected changes: `UsageReportsController::summary()` shapes journey groups and click counts with names (each name with its raw form) and bar widths, and passes uploader links. `usage_reports.php` gets tiles, pill links (`?uploader=`), "Shortcut ideas" cards first (heading "From → To", steps as an ordered list, clicks and times), and Most clicked as an ordered list with bars. Each readable name carries its raw form in `title` (hover or long-press). `TemplateRenderer` maps `usage_reports.php` to `tools.css`; new `usage-reports-*` rules in `tools.css`, single column under 720 px, two columns above.
- Verification approach: update `UsageReportsPageTest` for the new markup (pills with `aria-current`, "Shortcut ideas", readable steps, raw form in `title`, HTML in labels still escaped, 403 and flag-off unchanged); update `usage_reports_e2e.mjs`'s row lookup to the card markup.
- Estimate details: driver: template and CSS; uncertainty: theme coverage (icon and Bubblegum themes restyle tools pages).
- Risks or open questions: themes with their own Tools styling may need small overrides. Impact: odd colours in one theme. Early warning / validation: screenshot in default, Chicago and Bubblegum at 390 and 1280 px. Mitigation: use existing theme variables where the page already does.
- Canonical components/API contracts touched: `UsageJourneys::groups()`/`clickCounts()` (used unchanged); `TemplateRenderer` stylesheet map (one entry); `?uploader=` URLs (unchanged).

## Stage 3

**Estimate: 0.14**

- Goal: the readable Usage Log page.
- Dependencies: Stage 1.
- Expected changes: `UsageReportsController::log()` passes the viewer's username (or none) and the names table; `usage_log.php` adds the status card, the latest-steps list, "Show all N steps", the closed "Show exact text (what Upload sends)" disclosure holding the textarea, Upload as the primary button, and the attribution line; `data-usage-names` carries the table. `usage_log.js` gains read-only `entries()` (a copy, with seconds since the previous entry). `usage_log_page.js` builds sentences ("Opened Thread", "Clicked Tools in the navbar", "Pressed Save on Feature Flags"), inserts "paused N s" dividers at gaps of 20 s or more, shows the latest 10 with a toggle for all, and labels Upload "Upload N steps".
- Verification approach: extend `usage_log.mjs`: status card on, off and unavailable; sentences and a pause divider for a scripted session; the disclosure closed by default; the readable list and the exact text cover the same entries; the upload still equals the exact text. `UsageReportsPageTest`: attribution line signed in and out.
- Estimate details: driver: sentence building and tests; uncertainty: low.
- Risks or open questions: see the high risk.
- Canonical components/API contracts touched: `window.forumUsageLog` (adds `entries()`; `text()`, `state()`, recording unchanged).

## Stage 4

**Estimate: 0.06**

- Goal: check, document, close out.
- Dependencies: Stages 1–3.
- Expected changes: the usage reports concept gains the names table and the new page layouts; `UsageNamesTest` joins `tests/smoke.txt`.
- Verification approach: the smoke set plus this cycle's tests and `usage_log.mjs`, `usage_reports_e2e.mjs`, `tools_launcher.mjs`; screenshots of both pages at 390 and 1280 px in default, Chicago and Bubblegum, compared with the mock-up; `git diff --check`; final diff review.
- Estimate details: driver: screenshots across themes; uncertainty: low.
- Risks or open questions: none beyond the key risks.
- Canonical components/API contracts touched: [usage reports concept](../../concepts/usage_reports.md).

## Concepts touched

- Usage reports: changed (presentation; names table documented in it).

## Study Checkpoint

2026-11-15T05:15:25Z; Step 3 handoff; scope v1/A with the three confirmed mock-up decisions; starting revision `bd2b0fad` (main merged into the branch at Step 4 entry if it moved); R1/P2, single exposed Claude rater. Projected total **0.45 reaffirmed**; stage estimates 0.08, 0.15, 0.14, 0.06 are supplemental and non-additive. Not hours or measured effort; no approval to advance.

Status: **Approved Step 3** (maintainer, 2026-11-15T19:38Z).

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
