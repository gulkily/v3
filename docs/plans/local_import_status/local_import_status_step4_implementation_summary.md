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
