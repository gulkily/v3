> **Feature plan:** [Step 1](./local_import_progress_step1_solution_assessment.md) · [Step 2](./local_import_progress_step2_feature_description.md) · [Step 3](./local_import_progress_step3_development_plan.md) · [Step 4](./local_import_progress_step4_implementation_summary.md)

Cycle: `v3/local_import_progress`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_progress`; host ledger [local_import_progress.md](../effort_ledger/local_import_progress.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.40**

Concepts touched: [Local development import](../../concepts/local_development_import.md) and [Instance content import](../../concepts/instance_content_import.md) (both revised in Stage 5). [Internal task queue](../../concepts/internal_task_queue.md) and [Operator command progress](../../concepts/operator_command_progress.md) (used unchanged). Feedback: related `PF-20261117-F01`; a new item is allocated in Stage 5.

## Completion Contract

- Normal entry: a fresh clone, `./v3 start`, the home page, the prefilled source, Import.
- End-to-end outcome: the card shows the steps with counts, elapsed time, last-update age and time left, through to a line of counts and "Browse imported threads". Other pages load during the import. A reload shows the true state.
- Recovery: Retry after a failed download, Resume after an interrupted import, Try again and the worker command when no worker starts.
- Deployment: no schema change and no new flag. The local page exists only under `./v3 start`, so production behaviour changes only through the shared partial on Tools → Import, which keeps its markup.
- Release: merged to main, then a real zenmemes.com import by the maintainer on his own machine.

## Key Risks

- **High risk:** the local guard stands in for the operator check. Impact: a remote or cross-site request could queue an import. Early validation: Stage 2 runs every existing refusal case against each new request before the page uses them. Mitigation: every write and status request goes through `LocalDevelopmentImport::authorize()` unchanged; no new address is added.
- The task wants a requester identity that a fresh clone lacks. Impact: the queue refuses the request. Early validation: a Stage 1 test queues with the fixed requester name `local-development`. Mitigation: `InstanceImportTask::queue()` takes the name as given; fix there if it does not.
- Sharing the step list breaks Tools → Import. Impact: a regression on a working page. Early validation: Stage 1 ends with `tests/browser/tools_import.mjs` passing with no case changed. Mitigation: move markup without altering it.
- The first seconds look dead while the worker's 5-second loop comes round. Impact: doubt at the exact moment the request is about. Early validation: time the gap in Stage 5. Mitigation: the timer ticks from the click; shorten the loop only if the gap measures over 5 seconds.

## Stage 1

**Estimate: 0.10**

- Goal: both import pages can share one step list and one way to queue and read an import.
- Dependencies: none.
- Expected changes: the `import-run` block, the log and the not-started notice move from `templates/pages/tools_import.php` into `templates/partials/import_run.php`, markup unchanged. The queueing, worker start and state reading inside `ImportController` move to a new `Import/InstanceImportRequests` class: `queue(string $action, ?string $source, string $requester): array`, `startWorker(int $taskId): string`, `state(): array`, `publicState(): array`. `ImportController` keeps its operator, flag and same-origin checks and calls the new class.
- Verification approach: `./v3 test --smoke ImportPageTest InstanceImportTaskTest ImportProgressViewTest`, plus a new test that queues with the requester `local-development` and reads the state back. `node tests/browser/tools_import.mjs` with its 80 cases unchanged.
- Estimate details (extended mode only): driver: moving code without changing behaviour, under an existing browser check; uncertainty: low.
- Risks or open questions:
  - Impact: the requester and Tools → Import regression risks above.
  - Early warning / validation: the new queue test and the unchanged browser cases.
  - Mitigation: resolve both here; Stage 2 does not start otherwise.
- Canonical components/API contracts touched: `templates/pages/tools_import.php` (block moved out), `Http/ImportController.php` (delegates), `Import/InstanceImportTask.php` and `Import/InstanceImportStatus.php` (reused). New class because the logic sits inside a controller that enforces the operator gate.

## Stage 2

**Estimate: 0.10**

- Goal: Import on the local page returns at once and the card shows the steps on every load.
- Dependencies: Stage 1.
- Expected changes: `LocalDevelopmentPageController` queues `import` or `resume` through `InstanceImportRequests` after `authorize()`, then redirects to `/local/`. On GET it renders `import_run.php` from the same state. While an import runs, the form and the explanation are hidden. The pending sentence and its script are removed. A reload instruction that applies only without JavaScript is added to both import pages while an import runs. `Application.php` hands the controller the new class.
- Verification approach: `./v3 test --smoke LocalDevelopmentImportTest`: Import answers in under a second with a redirect; the next GET shows the first step current; a GET of a sample thread mid-import answers. Every existing refusal case (remote peer, proxy headers, bad Host, cross-site, no token) is run against Import, Resume and the status request added in Stage 3's shape. With scripts off in `tests/browser/local_development.py`, the current step changes without input.
- Estimate details (extended mode only): driver: replacing the blocking request with queue and redirect; uncertainty: medium, the no-JavaScript reload must not fire when scripts are on.
- Risks or open questions:
  - Impact: the local guard risk above.
  - Early warning / validation: the refusal cases, written before the wiring.
  - Mitigation: one authorization path for every local request.
- Canonical components/API contracts touched: `Http/LocalDevelopmentPageController.php`, `templates/pages/local_development.php`, `public/assets/local_development.js` (pending code removed), `Support/LocalDevelopmentImport.php` (`authorize()`, `token()` reused), `templates/partials/import_run.php`.

## Stage 3

**Estimate: 0.08**

- Goal: the steps, counts and timer update live, and a worker that doesn't start is handled.
- Dependencies: Stage 2.
- Expected changes: `import_control.js` reads its address and token from the page instead of assuming `/tools/import/`. The local page answers `status` and `start_worker` posts to `/local/` with the same JSON as Tools → Import. With no fresh worker, queueing starts one through `launchForOperator()`. The not-started notice and Try again show after 15 seconds.
- Verification approach: `LocalDevelopmentImportTest`: status JSON carries steps and timing; `start_worker` works only for a queued import; both refuse without the token. Browser check: the timer changes within 1.1 seconds, a step gains a count, and a reload shows the same current step. `tools_import.mjs` still passes.
- Estimate details (extended mode only): driver: one script serving two pages; uncertainty: low.
- Risks or open questions:
  - Impact: the script change breaks Tools → Import.
  - Early warning / validation: its browser cases after the change.
  - Mitigation: Tools → Import keeps its current address as the default.
- Canonical components/API contracts touched: `public/assets/import_control.js` (extended), the status payload from `publicState()` (unchanged), `TaskQueue/DetachedTaskQueueLauncher::launchForOperator()` (reused).

## Stage 4

**Estimate: 0.08**

- Goal: the card ends in a clear result, and failures offer the right next action.
- Dependencies: Stage 3.
- Expected changes: after a finished import the card shows one line of counts from the saved report, "Browse imported threads" and Import details. A partial import says it needs review. A failed task shows its reason, keeps the source and offers Retry. An interrupted import offers Resume. `LocalDevelopmentImport::run()` and its error mapping in the controller are removed.
- Verification approach: `LocalDevelopmentImportTest`, with its existing recovery cases moved to the queue: failed download then Retry, blocked publication then Resume, repeat import without duplicates, conflicts keeping local records. Each asserts the visible line and button.
- Estimate details (extended mode only): driver: rebuilding four end states on the task and report; uncertainty: medium, the old messages came from the command's exit code.
- Risks or open questions:
  - Impact: a failure reads worse than today's message.
  - Early warning / validation: compare each state's text with the current page before removing `run()`.
  - Mitigation: reuse Tools → Import's outcome wording.
- Canonical components/API contracts touched: `Import/InstanceImportStatus.php` (last report, reused), the count line in `tools_import.php` (moved into a shared partial if the same markup serves both), `Support/LocalDevelopmentImport.php` (`run()` removed).

## Stage 5

**Estimate: 0.08**

- Goal: prove it on the maintainer's path and record it.
- Dependencies: Stages 1–4.
- Expected changes: fixes from the real runs. Docs: both concept entries, the Browser entry section of `docs/runbooks/instance_content_import.md`, the UI copy checklist, and a new feedback item with a `Feedback:` trailer.
- Verification approach: a fresh worktree, `./v3 start`, a real zenmemes.com import from the home page in Chromium. Measure: click to first step, the longest gap without a visible change, and a sample thread's answer time mid-import. Repeat with `--no-worker`, then with `--no-worker` and `FORUM_PHP_CLI=/bin/false` for the not-started notice. Screenshots at 390 and 1280 in Gloss, Word97 and Bubblegum. `python3 tests/browser/local_development.py` and `tools_import.mjs`. `./v3 feedback check`, `git diff --check`, and a final diff review against this plan. Full suite not run.
- Estimate details (extended mode only): driver: real imports and the three worker conditions; uncertainty: medium, real runs found two fixes last time.
- Risks or open questions:
  - Impact: the first-seconds risk above.
  - Early warning / validation: the measured click-to-first-step time.
  - Mitigation: adjust within this stage if it exceeds 2 seconds to a visible step or 5 seconds to a claimed task.
- Canonical components/API contracts touched: `docs/concepts/local_development_import.md`, `docs/concepts/instance_content_import.md`, `docs/feedback/items/`, `tests/browser/local_development.py`.

## Enhancement requests

- None in this cycle.

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 2** by the maintainer; Step 1 defaults kept.
- 2026-11-22 UTC, Step 3 handoff: **0.40** projected total, reaffirmed. Stage work: 0.10, 0.10, 0.08, 0.08, 0.08 (not summed). Stage 5 owns the shared end-to-end checks and docs. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `22fc9f1`.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
