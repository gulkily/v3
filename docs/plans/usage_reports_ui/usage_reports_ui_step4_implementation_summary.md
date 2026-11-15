> **Feature plan:** [Step 1](./usage_reports_ui_step1_solution_assessment.md) · [Step 2](./usage_reports_ui_step2_feature_description.md) · [Step 3](./usage_reports_ui_step3_development_plan.md) · [Step 4](./usage_reports_ui_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; [debug log](./usage_reports_ui_debug_log.md))

Effort study: `railspike-effort` / `v3/usage_reports_ui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45** (Step 4 entry, reaffirmed from Step 3)
**Retrospective cycle scope: 0.46** (see Closeout)

Branch `plan/usage_reports_ui` with main `8314fae9` merged in. Stage originals below are copied from the approved Step 3 (R1, Claude single exposed rater); copying is not a new rating.

## Stage 1 - Readable names table

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `UsageNames` (`src/ForumRewrite/UsageReports/`): `page()` maps page kinds to names (`/threads/*` → "Thread", `/` → "Board"); `control()` maps `tab:` navbar keys (`tab:account` → "You menu"), `tool:` tones and `action:` hooks to labels, and passes free labels through. Unknown kinds and hooks fall back to their raw or humanized form. `covers()` reports whether a kind has a name or is raw on purpose (`/docs`, `/forte`, `/source`, whose segments are content). `forBrowser()` returns the pages, tabs and tools tables for the Usage Log page.
  - Tool page names and tool tones come from `ToolsPageSupport::registry()`, so new tools are named without touching the table.
- Verification:
  - New `UsageNamesTest` (registered in `tests/run.php`): 4 passed; covers known names, fallbacks, every route kind (48 sample paths plus every registry tool) named or raw on purpose, and the browser table.
  - `./v3 test` (smoke set, full output checked): 464 run, 464 passed.
- Notes:
  - The menu toggle is recorded by its `aria-label`, "Menu", because the `data-tab="account"` hook sits on the `<details>`, not the clicked `<summary>`; the table names `tab:account` anyway for links that carry it.
- Estimate details: original scope as approved; uncertainty low; no revisions.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Stage 2 - Usage Reports page

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - `UsageReportsController::summary()` shapes the data in words. Each journey card has a "From → To" heading ("Thread → Save on Feature Flags"; a menu or submit end reads "Save on Feature Flags"), its usual clicks as named steps, click count, times and whether it is a shortcut idea; every name keeps its raw form. Most clicked rows read "Menu — menu, on Thread" with a bar width relative to the top row. Uploader links are plain `?uploader=` URLs, with the current one marked `aria-current`.
  - `usage_reports.php` rewritten: uploader pills, three tiles (Reports, Steps, Shortcut ideas), "Shortcut ideas" cards first, then Most clicked as a ranked list with bars. Raw forms are in `title`. It no longer uses Visitor Statistics classes or `ZoomTable`.
  - `TemplateRenderer` loads `tools.css` on `usage_reports.php` (the missing stylesheet). New `usage-*` rules in `tools.css` use only existing theme variables (`--ink`, `--line`, `--panel`, `--active-*`, `--status-warning`). One column on phones; journeys and Most clicked side by side from 720 px.
- Verification:
  - `UsageReportsPageTest` updated for the new markup: headings and steps in words with raw forms on hover, HTML in labels escaped, one shortcut idea, tiles, pills and the filtered pill current, stylesheet loaded; 403 and flag off unchanged. With `UsageReportUploadTest` and `UsageNamesTest`: 13 passed.
  - `usage_reports_e2e.mjs` updated to find the card. It passed: a real three-click upload shows as "Thread → Feature Flags", three named steps, "never in one click".
  - Screenshots at 390 and 1280 px in the default, Chicago and Bubblegum themes: no horizontal overflow. The first pass showed "›" drawn inside each step pill and no gap between the columns; both fixed and re-shot.
  - `./v3 test` (smoke set, full output checked): 464 run, 464 passed.
- Estimate details: original scope as approved; no revisions.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Stage 3 - Usage Log page

**Original estimate: 0.14**
**Retrospective stage work: 0.15**

- Changes:
  - `usage_log.php` rebuilt:
    - a status card ("Recording" / "Not recording" / "Recording is unavailable", steps saved, the Record switch beside it);
    - one plain sentence on what is kept;
    - "Latest steps";
    - the textarea inside a closed "Show exact text (what Upload sends)" disclosure, with the format explanation;
    - Upload as the primary full-width button, Copy and Clear beneath;
    - an attribution line ("as ilyag", or "anonymously (you are not signed in)").
  - `data-usage-names` publishes `UsageNames::forBrowser()`.
  - `usage_log_page.js` builds the steps as sentences ("Opened Thread", "Clicked Tools in the navbar", "Pressed Save on Feature Flags") from `forumUsageLog.entries()` and the shared names. It adds "paused N s/min" dividers at gaps of 20 s or more, shows the latest 10 with "Show all N steps", and labels Upload "Upload N steps". The status line now carries only action messages. If Copy is blocked, it opens the disclosure and selects the text.
  - `usage_log.js`: new read-only `entries()` (a copy of the entries `text()` is made from, each with `dt`). Recording and `text()` are unchanged.
  - `UsageReportsController::log()` passes the names table and the viewer's username. New `usage-status`, `usage-log-*` rules in `tools.css`, on theme variables.
- Verification:
  - `tests/browser/usage_log.mjs` extended and passed:
    - status card off, on and unavailable;
    - one readable step per line of exact text, in words;
    - disclosure closed by default;
    - "Upload 3 steps";
    - the upload still equals the exact text;
    - Clear empties the steps;
    - a 245 s gap shows "paused 4 min";
    - latest 10 of 14, then "Show all";
    - names from the shared table ("You menu", "Feature Flags", "Usage Log").
  - `UsageReportsPageTest`: the disclosure, the anonymous and signed-in attribution, and the published names table. 18 passed with `UsageReportUploadTest`, `UsageNamesTest` and `UsageJourneysTest`.
  - `usage_reports_e2e.mjs` passed.
  - `./v3 test` (smoke set, full output checked): 464 run, 464 passed.
  - Screenshots at 390 and 1280 px in default, Chicago and Bubblegum. The first pass showed the switch label wrapping ("Recor/d") on phones and low-contrast card text on Bubblegum's dark active background; both were fixed and re-shot.
- Estimate details: original scope as approved; slightly over because of the two visual fixes; no revisions.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Stage 4 - Checks, docs and closeout

**Original estimate: 0.06**
**Retrospective stage work: 0.08**

- Changes:
  - [Usage reports concept](../../concepts/usage_reports.md): a Presentation paragraph (names table, the two page layouts), with "shortcut idea" and the readable log in the scenario.
  - `UsageNamesTest` added to `tests/smoke.txt`.
- Verification:
  - `./v3 test` (smoke set, full output checked): 464 run, 464 passed.
  - `./v3 test UsageReportsPageTest UsageReportUploadTest UsageNamesTest UsageJourneysTest`: 18 passed.
  - Browser: `usage_log.mjs`, `usage_reports_e2e.mjs` and `tools_launcher.mjs` (38 theme and width cases) passed.
  - Screenshots of both pages at 390 and 1280 px in default, Chicago and Bubblegum, compared with the mock-up: same structure; colours follow each theme instead of the mock-up's purple.
  - `git diff --check` clean; final review of the stage diff (16 files) found no debug leftovers or unrelated changes.
  - Not run: the full suite (left to the maintainer).
- Estimate details: original scope; slightly over because the screenshot pass ran twice per page after fixes.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Closeout

- Completion Contract: entry, end-to-end and recovery are done and tested locally. Release (both pages read on the maintainer's phone with the flag on, then merge) is pending the maintainer.
- Retrospective cycle scope: **0.46** against the 0.45 projection; the visual fixes found by screenshots (step separators, column gap, switch wrapping, Bubblegum contrast) were the only overrun.
- Differences from the mock-up: colours come from each theme's variables, not the mock-up's fixed purple; the Most clicked "where" text reads "menu, on Thread"; the menu toggle shows as "Menu" (its recorded label) rather than "You menu".
- Maintainer comfortable-fit judgment: pending.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
