> **Feature plan:** [Step 1](./local_import_progress_step1_solution_assessment.md) · [Step 2](./local_import_progress_step2_feature_description.md) · [Step 3](./local_import_progress_step3_development_plan.md) · [Step 4](./local_import_progress_step4_implementation_summary.md)

Cycle: `v3/local_import_progress`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_progress`; host ledger [local_import_progress.md](../effort_ledger/local_import_progress.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.40**

## Problem

After pressing Import on the local start page, the page shows one fixed sentence for up to five minutes. It does not show what the import is doing, whether it is still working, or how long is left, and the whole local site is frozen meanwhile.

## Approved direction

Step 1 was approved on 2026-11-22 ("approved step 1"). No option was named, so this follows the recommended Option A: the local start page queues the same import task as Tools → Import and shows the same progress. The open questions weren't answered, so the Step 1 defaults apply. Each is marked **(default)** below and can still be changed.

Concepts touched: [Local development import](../../concepts/local_development_import.md) (changed: queued instead of run in the page request; shows progress). [Instance content import](../../concepts/instance_content_import.md) (changed: a second browser entry for the same task). [Internal task queue](../../concepts/internal_task_queue.md) and [Operator command progress](../../concepts/operator_command_progress.md) (used unchanged). Feedback: related item `PF-20261117-F01`; a new item for this request is allocated in Step 4.

## User stories

- As a new user who just ran `./v3 start`, I want to see each step of the import as it happens, so that I know it is working.
- As that user, I want to see how long is left where the page can tell, so that I can decide whether to wait or come back.
- As that user, I want to browse the sample threads while the import runs, so that the wait isn't dead time.
- As that user, I want to come back to the page and find the progress still there, so that closing a tab costs nothing.
- As that user, I want a plain result at the end, so that I know what arrived and where to look.

## Core requirements

- Pressing Import, Retry or Resume on the local start page returns within about a second. The card then shows the step list Tools → Import shows: the steps in order, the current one marked, and counts where the import reports them.
- Under the steps, the card shows elapsed time, the age of the last update, and time left. Time left is per step where a rate exists, and "estimating time left" for the whole import when no earlier run exists **(default)**.
- The import runs in the task worker. Other pages load while it runs. Leaving and reopening the page shows the current progress. The sentence "Keep this page open" is removed.
- While the steps show, the form and the explanation are hidden **(default)**. There is no stop button **(default)**.
- When the import ends, the card shows one line of counts above "Browse imported threads", with the full output under Import details **(default)**. A failed or partial import shows its reason and the Retry or Resume button, as today.
- With `./v3 start --no-worker`, the page starts a worker itself. If none starts within 15 seconds, the card says so and offers Try again, as Tools → Import does.
- Without JavaScript, the page reloads itself every few seconds while an import runs **(default)**. Tools → Import gets the same.
- The local page keeps its own guard: `./v3 start` only, loopback only, token and same-origin. Deployed sites and remote visitors gain nothing. Tools → Import keeps its root-operator and flag gates.

## Delivery scope

- Work type: application change: the local start page's controller, template and script; one shared progress partial and script for both import pages; the local guard for a status request; tests, the browser check, concept entries and the runbook.
- Out of scope: a stop button for a running import; shipped typical step times; changes to what is imported; new `Progress:` lines; redesign of Tools → Import beyond sharing the partial and the no-JavaScript reload; imports on deployed sites.

## Completion boundary

- Normal entry: a fresh clone, `./v3 start`, the home page, the prefilled source, Import.
- End-to-end outcome: the card moves through every step to a finished line of counts, and an imported thread opens from "Browse imported threads". A sample thread opened mid-import loads.
- Recovery: a failed download offers Retry with the source kept. An interrupted import offers Resume. A worker that doesn't start offers Try again and shows the command. A reload at any point shows the true state.
- Release: merged to main and tried by the maintainer on his own machine with a real zenmemes.com import. No database change is planned.

## Known user or environment

The maintainer runs `./v3 start` on localhost and imports zenmemes.com. Entry: the home page's Import button. Failure to handle: a worker that never claims the task, which today's page cannot have. Next action unaided: Try again, or the shown worker command. Evidence: a real zenmemes.com import in a fresh worktree under `./v3 start`, with and without `--no-worker`, recorded in Step 4. A pass against a test source alone does not satisfy this.

## Risks

- **The local guard stands in for the operator check.** Impact: a mistake could let a remote or cross-site request queue an import. Earliest validation: before any wiring, list every request the local page will accept and run the existing refusal tests against each. Mitigation: reuse `LocalDevelopmentImport::authorize()` unchanged for every write and status request; Tools → Import's gates are not touched.
- **The task needs something a fresh clone lacks.** The task records who asked, and a fresh clone has no signed-in identity. Impact: the queue refuses the request. Earliest validation: queue one import from a fresh worktree as the first act of Step 3. Mitigation: record the local page as the requester.
- **Sharing the partial breaks Tools → Import.** Impact: a regression on a page that works. Earliest validation: `tests/browser/tools_import.mjs` (80 cases) after the extraction and before any local-page change. Mitigation: extract first, with no change in markup.
- **The first second looks dead.** The worker polls every 5 seconds. Impact: the user sees "Waiting for the task worker" and doubts it. Earliest validation: time the gap on a fresh start. Mitigation: the elapsed timer ticks from the click; shorten the wait only if it measures over 5 seconds.

## Shared component inventory

- `templates/pages/tools_import.php`, the `import-run` block (steps, timing, log, not-started notice): moved into one partial that both pages render. No second version.
- `public/assets/import_control.js`: reused for polling, the timer and Try again. Extended to take the page's own address and token instead of assuming Tools → Import.
- `Import/ImportProgressView.php`, `Import/InstanceImportTask.php`, `Import/InstanceImportStatus.php`: reused unchanged for steps, time left, queueing and the last result.
- `Http/ImportController.php`, `state()` and `publicState()`: the local page reads the same state. Its operator and flag checks stay where they are.
- `TaskQueue/DetachedTaskQueueLauncher::launchForOperator()`: reused for `--no-worker`.
- `Support/LocalDevelopmentImport.php`: `enabled()`, `authorize()` and `token()` reused unchanged. `run()`, the blocking run, is removed once nothing calls it.
- `templates/pages/local_development.php` and `public/assets/local_development.js`: changed in place. The pending sentence and its script go.
- `.import-steps`, `.import-timing` and related rules in `site.css`: reused; no new styles expected.
- `tests/LocalDevelopmentImportTest.php`, `tests/browser/local_development.py`, `tests/browser/tools_import.mjs`: extended.

## User flow

1. The user runs `./v3 start` and opens the home page.
2. They press Import with the prefilled source.
3. The card shows the steps, with the first one current and a timer ticking.
4. The steps advance with counts, and time left appears where it can be measured.
5. They open a sample thread, read it, and come back. The progress is still showing.
6. The card shows the counts and "Browse imported threads". They open an imported thread.

## Success criteria

- From the click to the first visible step: under 2 seconds on a fresh `./v3 start`.
- During a real zenmemes.com import, the card never goes more than 10 seconds without a visible change, counting the timer.
- A sample thread requested mid-import answers in under 2 seconds.
- A reload mid-import shows the same current step as before the reload.
- With `--no-worker` and no usable PHP command line, the card shows the not-started notice within 20 seconds.
- With JavaScript off, the current step changes on screen without the user pressing anything.
- Remote, proxied, cross-site and tokenless requests to the local page's import and status still get refused.
- `tests/browser/tools_import.mjs` passes unchanged in its existing 80 cases.

## Enhancement requests

- None in this cycle.

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 1** by the maintainer; Option A assumed, as recommended.
- 2026-11-22 UTC, Step 2 handoff: **0.40** projected total, reaffirmed from Step 1. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `22fc9f1`.
- 2026-11-22 UTC: **Approved Step 2** by the maintainer ("approved step 2"). Defaults kept.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
