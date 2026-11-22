> **Feature plan:** [Step 1](./local_import_progress_step1_solution_assessment.md) · [Step 2](./local_import_progress_step2_feature_description.md) · [Step 3](./local_import_progress_step3_development_plan.md) · [Step 4](./local_import_progress_step4_implementation_summary.md)

Cycle: `v3/local_import_progress`. Follows [local_start_import](../local_start_import/local_start_import_one_shot.md) (PR #284) and [import_without_worker](../import_without_worker/import_without_worker_step1_solution_assessment.md) (PRs #287, #288, #290), all merged.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_progress`; host ledger [local_import_progress.md](../effort_ledger/local_import_progress.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.40

## Original Query

Import public content

This feature on the frontend needs much better feedback.

"Importing… This can take several minutes. Keep this page open." is not good enough.

Please write Step 1.

[Maintainer in session, 2026-11-22.]

## Understood Intent

The text quoted is on the local start page (`/` and `/local/` under `./v3 start`), in the "Import public content" card. After pressing Import, the page should show what the import is doing, that it is still working, and roughly how long is left. Tools → Import already shows this; the local start page does not.

## Problem

The local start page runs the whole import inside one page request, so for up to five minutes it shows one fixed sentence and nothing else.

Three facts shape the options:

- `./v3 start` serves one request at a time. While the import request runs, every other page waits, in every tab. This is why the page says "Keep this page open."
- Tools → Import already has the feedback asked for: a step list with counts, an elapsed timer, "updated N s ago" and time left. It needs a root operator and the Import page flag, which a fresh clone has neither of.
- Since `import_without_worker`, `./v3 start` runs the task worker by default, and the import prints `Progress:` lines that worker records.

Concepts touched: [Local development import](../../concepts/local_development_import.md) (changed), [Instance content import](../../concepts/instance_content_import.md), [Internal task queue](../../concepts/internal_task_queue.md), [Operator command progress](../../concepts/operator_command_progress.md) (reused). Feedback: related item `PF-20261117-F01` (local start page); no item yet for this request.

## Option A — The local page queues the import and shows the Tools → Import progress

**Estimate: 0.40**
Main driver: moving the local page from a blocking request to the queued `instance_import` task, and sharing the step list between two pages. Uncertainty: medium; the local page's own guard (loopback and token) must stand in for the root-operator check.

Pressing Import queues the same task Tools → Import queues, and returns at once. The card then shows the same step list, counts, elapsed timer, last-update age and time left, updated every two seconds. The step list becomes one shared partial and script used by both pages.

- Pro: the feedback is real. Each step and count comes from the import's own `Progress:` lines.
- Pro: the site stays usable during the import. The reader can browse sample threads, or leave and come back to the same progress.
- Pro: "Keep this page open" goes away, because the worker runs the import, not the page.
- Pro: one progress display to maintain, already checked in Gloss, Word97 and Bubblegum.
- Con: the largest change. The local page's retry, resume and error states must be rebuilt on the queue.
- Con: with `./v3 start --no-worker` the page must start a worker, as Tools → Import does.

## Option B — Keep the blocking request, and stream the steps into the page

**Estimate: 0.25**
Main driver: sending the page in pieces while the import runs, and turning `Progress:` lines into visible steps as they arrive. Uncertainty: medium; browsers and themes handle a half-sent page differently.

The import still runs inside the page request. The page is sent as the import runs, so each step appears when it starts. It needs no polling and no JavaScript.

- Pro: a smaller change that keeps today's retry and resume handling.
- Con: the site still freezes for the whole import, and "Keep this page open" stays true.
- Con: a reload or closed tab loses the display, though the import carries on.
- Con: a second progress display, and a page that is not final on first paint, against `AGENTS.md`.
- Con: time left needs a script anyway, or it is left out.

## Option C — Send the local user to Tools → Import

**Estimate: 0.20**
Main driver: letting a loopback visitor on `./v3 start` use Tools → Import without a root key or the flag. Uncertainty: high; it loosens an operator gate.

The local start page keeps its card but the button opens Tools → Import with the source filled in. Tools → Import accepts the local visitor.

- Pro: the least new code, and exactly one import page.
- Con: an operator page gains a second way in. That is a security trade-off the local page was built to avoid.
- Con: the first page loses its one-click import, and a new user lands on a page made for operators.

## Option D — Better words and a timer only

**Estimate: 0.08**
Main driver: copy and a small script. Uncertainty: low.

The card shows a ticking elapsed timer and the names of the steps, with no real status behind them.

- Pro: an hour of work.
- Con: it cannot tell a working import from a stuck one, which is the complaint.

## Recommendation

Option A. The good feedback already exists one page away; the local page only lacks it because it runs the import a different way. Moving it to the queue also removes the frozen site, which no wording can fix. Option B is the fallback if the queue change proves too wide in Step 3. Options C and D are not recommended.

Vertical slice: clone, run `./v3 start`, open `http://127.0.0.1:8000/`, press Import with the prefilled zenmemes.com source. Within a second the card shows the steps with "Waiting for the task worker" current. It then moves through download, checking, planning, installing ("1,200 of 7,288"), views and images, with the timer ticking. Open a sample thread in the same tab during the install: it loads. Go back: the progress is still there. At the end the card shows the counts and "Browse imported threads".

## Open questions for the maintainer

- **Time left on a first import.** A fresh clone has no earlier run to learn from. Default: show time left per step where a rate exists, and "estimating time left" for the whole import. Alternative: ship typical step times for zenmemes.com as a starting guess.
- **Finished state.** Default: one line of counts (imported, conflicts, needs review) above "Browse imported threads", with the full output still under Import details.
- **Without JavaScript.** Default: the page reloads itself every few seconds while an import runs, so the steps still move. Tools → Import gets the same.
- **Stopping a running import.** Default: no stop button, as on Tools → Import today. Cancel stays for interrupted imports only.
- **The rest of the card while importing.** Default: hide the form and the three-sentence explanation while the steps show, as Tools → Import hides its form.

## Enhancement requests

- None in this cycle.

## Study snapshots and approval

- 2026-11-22 UTC, intake after code inspection (`local_development.php`, `local_development.js`, `LocalDevelopmentImport.php`, `LocalDevelopmentPageController.php`, `tools_import.php`, `ImportProgressView.php`, the `import_without_worker` summary): 0.40 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `22fc9f1`.
- Step 1 handoff: 0.40 for Option A (alternatives B 0.25, C 0.20, D 0.08; not summed). No implementation.
- 2026-11-22 UTC: **Approved Step 1** by the maintainer ("approved step 1"). No option named; Option A taken as recommended, with the Step 1 defaults for the open questions. Recorded in [Step 2](./local_import_progress_step2_feature_description.md#approved-direction).

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
