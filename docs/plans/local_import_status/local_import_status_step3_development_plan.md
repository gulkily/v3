> **Feature plan:** [Step 1](./local_import_status_step1_solution_assessment.md) · [Step 2](./local_import_status_step2_feature_description.md) · [Step 3](./local_import_status_step3_development_plan.md) · [Step 4](./local_import_status_step4_implementation_summary.md)

Cycle: `v3/local_import_status`. Branch: a new one from `origin/main` (PR #357 merged).

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_status`; host ledger [local_import_status.md](../effort_ledger/local_import_status.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.25**

Concepts touched: [Local development import](../../concepts/local_development_import.md) (revised in Stage 4). [Instance content import](../../concepts/instance_content_import.md) (used unchanged). Feedback: a new item is allocated in Stage 4 and linked from `PF-20261122-F01`.

## Completion Contract

- Normal entry: `./v3 start`, the home page, on a copy that has imported before.
- End-to-end outcome: the card states the source, the age and the result of the last import. "Check for new content" re-imports from that source with the live steps and ends in an updated status line.
- Recovery: a failed re-import shows its reason and Retry above the earlier status. "Change source" allows another source. A copy that never imported shows today's card.
- Deployment: no schema change, no new flag and nothing new stored. Tools → Import changes only in number formatting.
- Release: merged to main, then tried by the maintainer on his own copy, which already holds a zenmemes.com import.

## Key Risks

- **High risk:** the wrong run is shown as the last import. Impact: the status gives the wrong source, age or counts. Early validation: Stage 1 tests a page import and a terminal import in each order, and two imports within one second, before any markup. Mitigation: compare by recorded time; on a tie, use the page request's own output.
- "37 new" on a first import or a changed source. Impact: a misleading status. Early validation: Stage 1 tests first import, repeat and a different source. Mitigation: say "imported" unless an earlier finished import from the same source exists.
- A quieter held-back line hides a conflict with local work. Impact: a missed conflict. Early validation: Stage 2 renders the line with a conflict in the test data. Mitigation: conflicts are named in the line.
- Tools → Import changes by accident. Impact: a regression on a working page. Early validation: `ImportPageTest` and `tests/browser/tools_import.mjs` straight after the shared count line changes in Stage 2. Mitigation: only number formatting changes there.

## Stage 1

**Estimate: 0.08**

- Goal: one answer to "what was the last import?", correct for page and terminal imports.
- Dependencies: none.
- Expected changes: a new `Import/LastImportSummary` reader with `read(): ?array`. It returns the source, the finish time, where it ran (page or terminal), the counts, the held-back and conflict numbers, the elapsed seconds, and whether an earlier finished import came from the same source. It reads the newest finished page request through `InstanceImportRequests` and the saved report through `InstanceImportStatus`, and returns the newer. `InstanceImportRequests::resultFromLog()` also returns the elapsed seconds. `InstanceImportRequests` gains a way to list earlier finished requests with their sources.
- Verification approach: `./v3 test --smoke InstanceImportTaskTest` with a new `LastImportSummaryTest`: no import; page import only; terminal import only; each newer than the other; two imports within one second; first import, repeat and changed source; a failed newest request leaving the earlier good one as the answer.
- Estimate details (extended mode only): driver: two record kinds with different clocks and names; uncertainty: medium, the saved report's time comes from its directory name.
- Risks or open questions:
  - Impact: the wrong-run and "new" risks above.
  - Early warning / validation: the ordering and source tests.
  - Mitigation: resolve both here; Stage 2 does not start otherwise.
- Canonical components/API contracts touched: `Import/InstanceImportRequests.php` (extended), `Import/InstanceImportStatus.php` and `Import/InstanceImportTask.php` (reused unchanged). New class because neither existing reader covers both record kinds, and `InstanceImportStatus` must stay as Tools → Import uses it.

## Stage 2

**Estimate: 0.07**

- Goal: after an import, the card leads with the status line and a quiet held-back line.
- Dependencies: Stage 1.
- Expected changes: `LocalDevelopmentPageController` passes the summary to the template. `templates/pages/local_development.php` shows "Imported from <host> <age> · <N imported | N new | nothing new>" using the existing `$relativeTimestamp` helper, then "N held back for review" (with "M conflicts" when any), which opens Import details. The review alert and the raw count line leave the card. The `counts` part of `import_run.php` formats numbers with thousands separators.
- Verification approach: `./v3 test --smoke LocalDevelopmentImportTest ImportPageTest`: first import, repeat with nothing new, conflict, and a terminal import each give the expected line; no alert for held-back records alone; "duplicate" appears only inside Import details. `node tests/browser/tools_import.mjs` with its cases unchanged.
- Estimate details (extended mode only): driver: reshaping the finished states written last cycle; uncertainty: low.
- Risks or open questions:
  - Impact: the hidden-conflict and Tools → Import risks above.
  - Early warning / validation: the conflict case and the Tools checks.
  - Mitigation: conflicts named; the shared partial changes in formatting only.
- Canonical components/API contracts touched: `Http/LocalDevelopmentPageController.php`, `templates/pages/local_development.php`, `templates/partials/import_run.php` (`counts` part), `View/TemplateRenderer.php` (`$relativeTimestamp`, reused).

## Stage 3

**Estimate: 0.07**

- Goal: one action to re-import, with its cost stated, and failures shown above the last good status.
- Dependencies: Stage 2.
- Expected changes: with a summary present, the form shows one button, "Check for new content", posting the stored source. Beside it: "Downloads everything again; last time took 17 s." The source field moves into a native "Change source" disclosure; a changed source reads Import. With no summary, the form is today's. A failed or interrupted newest request shows its reason with Retry or Resume, and the status line of the last good import stays below. `import_control.js` is unchanged; the status carries `data-import-result` so the steps replace it.
- Verification approach: `LocalDevelopmentImportTest`: one click re-imports the stored source; the source field is inside a closed disclosure; a failed re-import shows the reason, Retry and the earlier status; a copy with no import shows today's form. `python3 tests/browser/local_development.py`, extended: the button, the disclosure, the steps replacing the status, and no overflow at 390 and 1280 px in every offered theme, scripts on and off.
- Estimate details (extended mode only): driver: form states for import, re-import, retry and resume; uncertainty: low.
- Risks or open questions:
  - Impact: a required source field inside a closed disclosure could block submission in some browsers.
  - Early warning / validation: the browser check submits with the disclosure closed, scripts on and off.
  - Mitigation: the button carries the stored source itself; the field is used only when the disclosure is open.
- Canonical components/API contracts touched: `templates/pages/local_development.php`, `Http/LocalDevelopmentPageController.php` (source choice), `public/assets/import_control.js` (reused unchanged), `tests/browser/local_development.py`.

## Stage 4

**Estimate: 0.06**

- Goal: prove it on the maintainer's path and record it.
- Dependencies: Stages 1–3.
- Expected changes: fixes from the real runs. Docs: `docs/concepts/local_development_import.md`, the start-page lines in `docs/reference/v3_cli.md`, the UI copy checklist, a new feedback item with a `Feedback:` trailer, and a note on `PF-20261122-F01`.
- Verification approach: a fresh copy under `./v3 start` with one real zenmemes.com import. Then check the card: after that page import; after `./v3 import-instance https://zenmemes.com` from the terminal; after "Check for new content"; and after a failed re-import from an unreachable source. Also a copy whose import was made by the code before this cycle. Screenshots at 390 and 1280 px in Gloss, Word97 and Bubblegum. `./v3 feedback check`, `git diff --check`, and a final diff review against this plan. Full suite not run.
- Estimate details (extended mode only): driver: four real states on one copy; uncertainty: low.
- Risks or open questions:
  - Impact: a copy imported before this work has records the reader does not expect.
  - Early warning / validation: the older-copy check.
  - Mitigation: fall back to the saved report, then to today's form.
- Canonical components/API contracts touched: `docs/concepts/local_development_import.md`, `docs/reference/v3_cli.md`, `docs/feedback/items/`.

## Enhancement requests

- Carried from Step 2, both not approved: the board as home page after an import; automatic re-import.

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 2** by the maintainer; defaults kept.
- 2026-11-22 UTC, Step 3 handoff: **0.25** projected total, reaffirmed. Stage work: 0.08, 0.07, 0.07, 0.06 (not summed). Stage 4 owns the shared end-to-end checks and docs. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` with PR #357 merged.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
