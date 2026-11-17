# Import-instance GUI Step 2 feature description

> **Feature plan:** [Step 1](./import_instance_gui_step1_solution_assessment.md) · [Step 2](./import_instance_gui_step2_feature_description.md) · [Step 3](./import_instance_gui_step3_development_plan.md) · [Step 4](./import_instance_gui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_instance_gui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.60**

## Approved direction

Step 1 Option A with Option C's "last import" view, approved 2026-11-16 ("approved step 1"). The maintainer's thread replies of 2026-11-13 adjust it:

- "we should remember instance options": the page remembers sources and offers them again.
- "doing a big import should be root-only, but doing a user-level import should be for any approved user": this cycle builds the root-only full import. A user-level import is a separate cycle.
- "preview: in v2 (make a separate task)": no Preview button in this cycle.
- "detailed report that's hidden in advanced": the per-file report sits under Advanced.
- "it could be client-mediated just as much as server-mediated": noted. This cycle keeps the approved worker-run import; a browser-run import is not in scope.

Sources: [thread](https://zenmemes.com/threads/thread-20261113042243-1cbe8b8a).

## Problem

Importing another instance's content needs SSH and `./v3 import-instance`. An operator on a phone cannot start an import, watch it, resume it or see why the last one stopped.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (new entry point; merge rules unchanged), [Internal task queue](../../concepts/internal_task_queue.md) (new import task type), [Operator command progress](../../concepts/operator_command_progress.md), [Tools launcher](../../concepts/tools_launcher.md) (new Import destination). Proposed inventory addition: none; the import concept gains a "Browser entry" line.

## User stories

- As a root operator, I want to import an instance from Tools so that I don't need SSH.
- As a root operator, I want to pick a source I used before so that I don't retype it on a phone.
- As a root operator, I want to watch progress and read the result so that I can tell a working import from a stuck one.
- As a root operator, I want to resume an interrupted import from the page so that a failure doesn't send me back to SSH.
- As a root operator, I want per-file details out of the way but reachable so that the result stays readable.

## Core requirements

- Only a signed-in root-approved operator sees the controls or can start an import; the server enforces this, as on Tools → Deploy.
- The task worker runs the same import code as the command, with the same limits, locking, exclusions and conflict rules.
- The page remembers each source after a started import and lists them for reuse. The list lives in private state in the project's `Key: value` format and never enters public downloads.
- The page shows live progress, then the result counts, image status and grouped review causes. Per-file entries sit under Advanced.
- When an interrupted import is pending, the page offers Resume and hides Import.

## Delivery scope

Work type: application change, behind a new feature flag that is off by default.

## Completion boundary

- Entry: Tools → Import.
- Outcome: a root operator picks or types a source, presses Import, watches it finish, and reads the result. The imported content then appears on the site, as after the command.
- Recovery: a worker that isn't running shows a plain message, as Deploy does. An interrupted import shows Resume. A failed import shows its error, and the operator can start it again. A second Import while one runs is refused.
- Release: merged, deployed, flag enabled on zenmemes.com, and one real import run from the page.

## Risks

- **A long import holds the worker.** Other queued tasks wait for up to the 300-second limit. Validate with a loopback import in a local run. Mitigation: show "waiting for import" on Deploy and accept the wait for this slice.
- **Progress capture from the worker.** The command prints progress to the terminal; the task needs it in a form the page can read. Validate in Step 3 by finding where task status is stored. Mitigation: store recent lines with the task and keep the full output in private state.
- **Supervisor in worker context.** The command's time-limit supervisor expects its own process. Validate early by running the import inside the worker locally. Mitigation: the task runs the existing script as a child process if direct calls conflict.
- **Remembered sources leak.** A list of instances is operator data. Mitigation: keep it under private state and check the export allowlist.

## Shared component inventory

- Tools → Deploy page, `src/ForumRewrite/Http/DeployController.php` and `templates/pages/deploy.php`: reuse its root gate, same-origin check, JSON status polling and worker heartbeat display. The Import page copies the pattern; it does not extend Deploy.
- Task queue, `src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php` and `TaskQueueWorker.php`: add one import task type beside the existing types.
- Import code, `scripts/import_instance.php` and `src/ForumRewrite/Import/`: reuse unchanged as far as possible. The result report and pending-resume state under `.git/instance-import/` feed the "last import" view.
- Tools navigation, `src/ForumRewrite/Tools/ToolsPageSupport.php`, and icons in `public/assets/theme-nav-icons.css`: add Import in the operator group with icons in both sets.
- Feature flags, `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: add the flag.

## User flow

1. A root operator opens Tools → Import.
2. The page shows the last import's result, or Resume if one is pending.
3. The operator picks a remembered source or types a URL or hostname, then presses Import.
4. The page shows progress lines while the worker runs.
5. The page shows the result counts, image status and grouped causes. Advanced shows per-file entries.

## Success criteria

- A loopback import started from the page adds the same records as the command does for the same source.
- An import interrupted on purpose shows Resume, and Resume completes it.
- A non-root viewer gets no controls, and a direct request from them is refused.
- A source used once appears in the list on the next visit.
- Phone and desktop layouts pass the verification list in `CONTRIBUTING.md` in each affected theme.

## Enhancement requests

- User-level import for any approved user: separate cycle, pending.
- Preview (dry run) on the page: separate cycle, "v2".
- Browser-run import: noted, not scheduled.
- Link to the Step 1 document on the website: separate request, answered in the thread.
- Link to the Claude conversation, encrypted for the maintainer: separate request, answered in the thread.

## Study snapshots

- Step 2 handoff: 0.60 projected total, revised down from 0.70 because Preview moves to a later cycle; the remembered-sources list adds a little back. Scope v2, baseline `c98b5221`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
