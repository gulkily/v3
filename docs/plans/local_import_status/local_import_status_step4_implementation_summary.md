> **Feature plan:** [Step 1](./local_import_status_step1_solution_assessment.md) · [Step 2](./local_import_status_step2_feature_description.md) · [Step 3](./local_import_status_step3_development_plan.md) · [Step 4](./local_import_status_step4_implementation_summary.md)

Cycle: `v3/local_import_status`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_status`; host ledger [local_import_status.md](../effort_ledger/local_import_status.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.25**

Step 3 approved 2026-11-22 ("approved step 3"). Branch `feat/local_import_status` from `origin/main` `d9e9a91` (PR #357 merged). Planning commit `773c31c`. Step 4 entry: 0.25 reaffirmed, scope v1; stage originals copied from Step 3.

## Stage 1 - One answer to "what was the last import?"

**Original estimate: 0.08**
**Retrospective: 0.06**

- Changes:
  - New `src/ForumRewrite/Import/LastImportSummary.php`. `read(?string $preferRunId)` returns the newest finished import: source, host, time, status, counts, records imported, records held back, conflicts, and whether an earlier import came from the same source.
  - `InstanceImportRequests::resultFromLog()` also returns the elapsed seconds and the run the output belongs to.
- Verification:
  - `./v3 test --smoke LastImportSummaryTest InstanceImportTaskTest`: passed (smoke set included). New `LastImportSummaryTest` covers: no import, previews and canceled runs; a later run that sorts earlier by name; the reverse order; two reports written in the same second, settled by the run the output names; a changed source; a large report; an unreadable report.
  - Read against a real copy holding two zenmemes.com imports: the second run, "duplicate 8814", repeat true, in 1.7 ms.
  - Full suite not run.
- Notes:
  - **Deviation from Step 3, simpler:** the plan had the reader merge page request records with saved reports. Reading the code showed every import that reaches the merge saves a report, however it was started. So the saved reports alone cover page and terminal imports, and `InstanceImportRequests` needed no list of earlier requests.
  - Runs are ordered by when their report was written, not by name. PHP reads that time to the second, so a tie is settled by the run the newest page output names, as planned. Two terminal imports finishing in one second can still tie.
  - The time shown is when the report was written. That is the end of the merge, a few seconds before images finish.
  - Only the start of each report is read. Real reports are megabytes of per-file entries, and the start page reads this on every visit.
  - Held back counts conflict, invalid and unsupported records. Excluded records are left out by rule, so they are not counted as held back.
- Estimate details: retrospective lower; one record kind instead of two.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 2 - Status line and quiet held-back line

**Original estimate: 0.07**
**Retrospective: 0.07**

- Changes:
  - `LocalDevelopmentPageController` passes `LastImportSummary::read()` to the page, preferring the run the newest page output names.
  - `templates/pages/local_development.php` leads with "Imported from **host** <age> · N imported | N new | nothing new", using the existing `$relativeTimestamp` helper. The age is a `time` element with the full date as its title.
  - Under it, one disclosure. Its label is the held-back line ("356 held back for review · 4 conflicts", plus "some images not copied" when that applies), or "Import details" when nothing was held back. Inside: the full count line, and the import's output when the newest page request is the same run.
  - The review alert and the bare count line are gone from the card. "Browse imported threads" shows whenever a finished import exists.
  - A failed request keeps its reason as the alert, with its own details, above the last good status.
  - The `counts` part of `import_run.php` prints thousands separators, on both import pages.
- Verification:
  - `./v3 test --smoke LocalDevelopmentImportTest ImportPageTest LastImportSummaryTest`: 499 run; 498 passed and 1 failed on the first run. The failure was `ImportPageTest` expecting "conflict 10000"; it now expects "conflict 10,000", the intended change. Rerun of `ImportPageTest LocalDevelopmentImportTest`: passed.
  - `LocalDevelopmentImportTest` asserts: no status on a copy whose only import failed; "1 imported" after the first import; "nothing new" on a repeat with "duplicate" only inside the details; "1 held back for review · 1 conflict" with no alert on a conflict; and "1 new" after an import run from the terminal, with no page output shown for it.
  - `node tests/browser/tools_import.mjs`: passed, 80 cases unchanged.
  - Full suite not run.
- Notes:
  - The held-back line is the disclosure's own label, so "opens Import details" needs no link or script.
  - The button still reads "Import again" here; Stage 3 replaces the form.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 3 - One re-import action, its cost, and Change source

**Original estimate: 0.07**
**Retrospective: 0.10**

- Changes:
  - On a copy that has imported, the form is one button, "Check for new content", carrying the copy's source in a hidden field. Under it: "Downloads everything again; last time took 17 s." The time comes from the last run's own output and is left out when this page has none (a terminal import).
  - The source field sits in a "Change source" disclosure with its own Import button. A refused request from it reopens it with what was typed.
  - After a failed request the button reads "Retry import" and carries the failed source. The reason shows above the last good status.
  - A copy that never imported, and an interrupted import, keep the previous form. The explanation paragraph shows only there.
  - `InstanceImportRequests::steps()` marks the first step current once the worker has claimed an import and before its first line.
  - `tests/browser/local_development.py` covers the status card.
- Verification:
  - `./v3 test --smoke LocalDevelopmentImportTest ImportPageTest InstanceImportTaskTest LastImportSummaryTest ImportProgressViewTest`: passed (smoke set included).
  - `LocalDevelopmentImportTest` asserts: the button and its hidden source; the cost line with a time; the closed disclosure holding the field; no explanation paragraph; a failed re-import from another source with its reason, Retry, the failed source, and the earlier status below the alert; a refused source reopening the disclosure.
  - `python3 tests/browser/local_development.py`: passed 6 times in a row after the fixes below. It submits Change source and Retry with scripts on and off, in every offered theme at 390 and 1280 px, with no overflow.
  - `node tests/browser/tools_import.mjs`: passed, 80 cases unchanged.
  - Full suite not run.
- Notes:
  - **Found by the browser check:** with scripts off, a page loaded just after the worker claimed the import showed no current step until the next reload. The list had every step pending. The first step is now current in that window. This is shared with Tools → Import.
  - **Two flaky assertions fixed in the browser check.** One was there before this cycle: it read the board right after a click without waiting for the page (about 1 run in 5 failed). The other, from the last cycle, assumed the first paint never shows "Downloading".
  - Two forms, not one: the script follows only the main form. Change source posts natively, lands on the page with the steps, and the script takes over from there.
- Estimate details: retrospective higher for the no-current-step defect and the two flaky assertions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 4 - Real copy and docs

**Original estimate: 0.06**
**Retrospective: 0.06**

- Changes:
  - The time in the cost line keeps its unit on the same line ("9 s" no longer wraps between the number and the unit at 390 px).
  - Docs: `docs/concepts/local_development_import.md` describes the status card and where the status comes from; `docs/reference/v3_cli.md` and the UI copy checklist are updated.
  - New feedback item `PF-20261122-F02`. `PF-20261122-F01` notes PR #357 as merged. `PF-20261122-B02` notes that the local page no longer depends on the ordering it describes.
- Verification, on one copy under `./v3 start` holding real https://zenmemes.com content, in Chromium at 390 px:
  - **A copy imported before this work:** the copy ran `origin/main` `d9e9a91`, imported from the page, then was switched to this branch. The card read "Imported from zenmemes.com just now · 8,837 imported", "360 held back for review · 4 conflicts", Browse imported threads, Check for new content, "Downloads everything again; last time took 32 s." Passed.
  - **After `./v3 import-instance https://zenmemes.com` from the terminal:** "… just now · nothing new", the same held-back line, and "Downloads everything again." with no time. Passed.
  - **After Check for new content:** one click, the live steps, then "… just now · nothing new" and "last time took 9 s." in 12 seconds. Passed.
  - **After a failed re-import from an unreachable source:** the reason, its details, then the zenmemes.com status below, and Retry import. Passed.
  - No page errors and no sideways overflow in any of these. Screenshots reviewed: Gloss 390 and 1280, Word97 390 and 1280, Bubblegum 390, with Change source open.
  - `./v3 test --smoke LocalDevelopmentImportTest ImportPageTest InstanceImportTaskTest LastImportSummaryTest ImportProgressViewTest`: 510 run, 510 passed.
  - `python3 tests/browser/local_development.py` and `node tests/browser/tools_import.mjs` (80 cases): passed.
  - `./v3 feedback check`: 0 problems. `git diff --check`: clean. Concept links resolve.
  - Final diff review against Step 3: only files named in Stages 1–4, plus `Application.php` (one constructor argument) and `tests/ImportPageTest.php` (the separator expectation). No dependency, schema, flag or stored state was added.
  - Full suite not run.
- Not verified:
  - The maintainer's own copy. That is the release condition.
  - "N new" with a real nonzero number: zenmemes.com had nothing new between the runs. Tests cover it with a test source.
  - An age older than a week, where the existing helper prints a date. The line then reads "Imported from zenmemes.com Nov 12, 2026 · …".
  - Browsers other than Chromium, and physical phones.
- Notes, for follow-up outside this cycle:
  - After a failed import from a *changed* source, Retry and the Change source field both hold the failed source. Going back to the copy's own source means typing it.
  - The card's heading still reads "Import public content" when it is showing a status.
  - Step 1's Option B (the board as home page after an import) is the proposed next cycle.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Enhancement requests

- Carried from Step 2, both not approved and not started: the board as home page after an import; automatic re-import.
- None received during Step 4.

## Completion Contract check

- Normal entry and end-to-end outcome: passed locally on a copy with real zenmemes.com content.
- Recovery: failed re-import with Retry above the earlier status, Change source, and the never-imported form all passed.
- Deployment: no schema change, no new flag, nothing new stored. Tools → Import changes in number formatting and in the first step being current after the worker's claim.
- Release: pending. PR https://github.com/gulkily/v3/pull/370 is open, not merged; the maintainer's own run is outstanding.

## Study closeout

- Retrospective scope assessment (not a measured actual): **0.27** for scope v1 as delivered, against the 0.25 projection. Stage 1 was smaller than planned; Stage 3 was larger for one defect and two flaky assertions.
- Stage retrospectives: 0.06, 0.07, 0.10, 0.06 (not summed).
- Maintainer comfortable-fit judgment: pending. Acceptance: pending.
- Claude single exposed rater, R1 (hashes not re-verified this session), P2, 2026-11-22 UTC. Measured time unknown.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
