> **Feature plan:** [Step 1](./local_import_status_step1_solution_assessment.md) · [Step 2](./local_import_status_step2_feature_description.md) · [Step 3](./local_import_status_step3_development_plan.md) · [Step 4](./local_import_status_step4_implementation_summary.md)

Cycle: `v3/local_import_status`. Follows [local_import_progress](../local_import_progress/local_import_progress_step1_solution_assessment.md), PR #357, merged 2026-11-22. Branch: a new one from `origin/main`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_status`; host ledger [local_import_status.md](../effort_ledger/local_import_status.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.25**

## Problem

After an import, the card on the local start page shows a line of counts and the same form with "Import again". Nothing says where our copy came from, how old it is, or what importing again would do.

## Approved direction

Step 1 was approved on 2026-11-22 ("approved step 1"). No option was named, so this follows the recommended Option A: the card becomes a status of our copy, and the start page stays the home page. The open questions weren't answered, so the Step 1 defaults apply. Each is marked **(default)** below and can still be changed.

Concepts touched: [Local development import](../../concepts/local_development_import.md) (changed: the card reports the last import and offers one re-import action). [Instance content import](../../concepts/instance_content_import.md) (used unchanged). Feedback: no item yet; one is allocated in Step 4, linked from `PF-20261122-F01`.

## User stories

- As someone running a local copy, I want to see where its content came from and how old it is, so that I know what I am looking at.
- As that person, I want one button to bring in what is new, so that I don't retype a source I already chose.
- As that person, I want a repeat import to say how much was new, so that "duplicate 8814" doesn't read like a failure.
- As that person, I want to know a re-import downloads everything, so that I can decide whether it is worth it now.
- As that person, I want held-back records mentioned quietly, so that the page doesn't warn me on every visit about something I can't fix.

## Core requirements

- After a finished import, the card leads with one status line: the source's host name, how long ago, and what came in. A first import reads "8,813 imported"; a repeat reads "37 new" or "nothing new". Numbers carry thousands separators **(default)**.
- The status covers the last import only **(default)**. It also counts an import run from the terminal, using whichever is newer **(default)**.
- One button, "Check for new content" **(default)**, imports again from the same source. Beside it, one quiet line says the cost: "Downloads everything again; last time took 17 s." **(default)**
- The source field is under "Change source". Before any import, the card is as it is today: explanation, source and Import.
- Records held back show as one line, "1,123 held back for review" **(default)**, which opens Import details. It is not an alert. "Browse imported threads" stays whenever records are in.
- A failed or interrupted import shows today's reason with Retry or Resume. The last good status stays visible below it.
- While an import runs, the steps replace the status, as today. Everything is server-rendered and reads the same without JavaScript.
- Nothing new is stored. The status is read from records imports already leave.

## Delivery scope

- Work type: application change: the local start page's controller and template, a reader for the last import's status, the count line shared with Tools → Import, tests, the browser check, the concept entry and a feedback item.
- Out of scope: the board becoming the home page after an import (Option B, proposed next); automatic re-import (Option C); sync; import history beyond the last run; any change to what an import does; the three filed follow-ups `PF-20261122-B01`–`B03`, except where this work must read around them.

## Completion boundary

- Normal entry: `./v3 start`, the home page, on a copy that has imported before.
- End-to-end outcome: the card states source, age and result; "Check for new content" runs a re-import with the live steps and ends in an updated status line.
- Recovery: a failed re-import shows its reason and Retry, with the earlier status still shown. "Change source" allows a different source. A copy that never imported sees today's form.
- Release: merged to main and tried by the maintainer on his own machine, on a copy that already holds a zenmemes.com import.

## Known user or environment

The maintainer runs `./v3 start` on localhost with zenmemes.com imported. Entry: the home page. Condition to handle: his copy's last import may predate this work, or may have been run from the terminal. Next action unaided: the card still shows a status from the saved report, or today's form if nothing is recorded. Evidence: a fresh copy with one real zenmemes.com import, then the card checked after a page import, after a terminal import, and after a re-import. A pass against a test source alone does not satisfy this.

## Risks

- **The wrong run is shown as the last import.** Two records exist per import, and the saved-report reader orders runs by a name precise to one second (`PF-20261122-B02`). Impact: the status names the wrong counts or age. Earliest validation: before any markup, a test with a page import and a terminal import in each order. Mitigation: compare by recorded time, and prefer the page request's own output when times tie.
- **"37 new" is wrong for a first import or a changed source.** Impact: a misleading status. Earliest validation: tests for first import, repeat, and a different source. Mitigation: say "imported" unless an earlier finished import from the same source exists.
- **A quieter held-back line hides a real problem.** Impact: a user misses conflicts with local work. Earliest validation: review the line with a conflict in the test data. Mitigation: conflicts are named in the line ("4 conflicts"); the rest is counted together.
- **Tools → Import changes by accident.** Thousands separators land in the shared count line. Earliest validation: `tests/browser/tools_import.mjs` and `ImportPageTest` after the change. Mitigation: change the shared partial only for number formatting.

## Shared component inventory

- `Import/InstanceImportRequests.php`: `state()`, `latestLog()` and `resultFromLog()` give the last page-started import's source, time, counts and failure. Extended with the elapsed time and with the previous finished import's source, to tell "new" from "imported".
- `Import/InstanceImportStatus.php`: the saved report's source, time and counts, for terminal imports. Reused unchanged.
- `Import/InstanceImportTask.php`: request records (`Source`, `Requested-At`). Reused unchanged.
- `Http/LocalDevelopmentPageController.php` and `templates/pages/local_development.php`: changed in place. No second card.
- `templates/partials/import_run.php`: the `counts` part gains thousands separators for both pages; the steps are reused unchanged.
- `View/TemplateRenderer.php`, `$relativeTimestamp`: the existing "3 hours ago" helper. Reused; no new time wording.
- `public/assets/import_control.js`: reused. `data-import-result` already hides the status while a new import runs.
- A native `details` element for "Change source", as Import details uses. No new script or styles expected.
- `tests/LocalDevelopmentImportTest.php`, `tests/InstanceImportTaskTest.php`, `tests/browser/local_development.py`: extended.

## User flow

1. The user opens the home page of a copy that imported zenmemes.com earlier.
2. The card reads "Imported from zenmemes.com 2 hours ago · 8,813 imported", with a held-back line under it.
3. They press "Check for new content". The steps run.
4. The card reads "Imported from zenmemes.com just now · 37 new".
5. Later they open "Change source", type another instance and import from it.

## Success criteria

- On a copy with a finished import, the first paint shows the source's host name, a relative age and a count, with no alert for held-back records alone.
- A repeat import with nothing new reads "nothing new", and the word "duplicate" does not appear outside Import details.
- A terminal import newer than the last page import is the one the card reports, and the reverse.
- One click starts a re-import from the stored source; the source field is not visible until "Change source" is opened.
- After a failed re-import, the reason and Retry show, and the earlier status line is still on the page.
- A copy that never imported shows today's card unchanged.
- `tests/browser/tools_import.mjs` passes with its existing cases, and counts there show separators.
- No sideways overflow at 390 and 1280 px in every offered theme, scripts on and off.

## Enhancement requests

- **Request:** the board as home page after an import (Step 1 Option B). **Disposition:** separate work, proposed as the next cycle. **Approval status:** not approved.
- **Request:** automatic re-import (Step 1 Option C). **Disposition:** not recommended before sync. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 1** by the maintainer; Option A assumed, as recommended.
- 2026-11-22 UTC, Step 2 handoff: **0.28** considered and **0.25** kept. The terminal-import default adds a second source of truth; the rest is unchanged. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` with PR #357 merged.
- 2026-11-22 UTC: **Approved Step 2** by the maintainer ("approved step 2"). Defaults kept, including conflicts named in the held-back line.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
