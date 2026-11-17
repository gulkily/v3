# Import-instance GUI Step 3 development plan

> **Feature plan:** [Step 1](./import_instance_gui_step1_solution_assessment.md) · [Step 2](./import_instance_gui_step2_feature_description.md) · [Step 3](./import_instance_gui_step3_development_plan.md) · [Step 4](./import_instance_gui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_instance_gui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.65**

Step 2 approved 2026-11-16 ("approved step 2"). In the same message the maintainer asked for a cancel command as a prerequisite. It shipped as its own one-shot, [`v3/import_cancel`](../import_cancel/import_cancel_one_shot.md) (PR #285), so the page offers Cancel beside Resume. This plan assumes that PR is merged before Stage 4.

## Completion Contract

- Entry: Tools → Import, for a signed-in root-approved operator with the feature flag on.
- Outcome: the operator picks or types a source and presses Import. The task worker runs `./v3 import-instance`, and the page shows its progress and then the result. The imported content appears on the site.
- Recovery: with no worker running, the page says so. A pending interrupted import shows Resume and Cancel instead of Import. A failed task shows its error, and the form stays usable for another try. A second request while one is queued or running is refused.
- External check: one real import from the page on zenmemes.com after deploy and flag enable.
- Release: merged, deployed, flag on, external check done.

## Key Risks

- **High risk:** the worker runs an import that writes production content. Impact: wrong destination or doubled work. Validation: Stage 1 runs a loopback import through the worker in a test workspace. Mitigation: the handler calls the same script with destination paths from deployment settings only, never from the request, and the import's own locks stay in force.
- A long import holds the worker for up to 300 seconds. Validation: Stage 1 confirms the import's limit sits under the queue's 900-second abandoned-task recovery. Mitigation: accept the wait; Deploy shows the worker as busy.
- The import's time-limit supervisor needs its own process. Mitigation: the handler starts the script as a child process instead of calling the runner in-process.
- Remembered sources must stay private. Validation: Stage 2 test checks the file sits outside the content repository and the download allowlist.

## Stage 1
**Estimate: 0.18**
- Goal: the task worker can run a queued import, resume or cancel and record its progress.
- Dependencies: PR #285 for the cancel action; the rest does not depend on it.
- Expected changes: a new `instance_import` task type. A private request file, `state/private/instance_import/request.txt`, in `Key: value` format (`Action`, `Source`, `Requested-By`, `Requested-At`), written when the task is queued. A handler that starts `scripts/import_instance.php` with that action, passes each output line to `recordTaskProgress`, keeps the full output in a private log beside the request, and marks the task done or failed. Exit code 2 counts as done with review needed. Planned signatures: `InstanceImportTask::queue(SqliteTaskQueueStore $store, string $action, ?string $source, string $actor): array` and `InstanceImportTask::run(int $taskId): array`.
- Verification approach: new `InstanceImportTaskTest` runs a loopback import, a refused second queue and a failed source through the worker. `./v3 test --smoke InstanceImportTaskTest`.
- Estimate details: driver: child-process output capture; uncertainty: how the supervisor reports a timeout to a parent.
- Risks or open questions:
  - Impact: a stuck child keeps the task running.
  - Early warning / validation: a test with a tiny `--time-limit`.
  - Mitigation: the handler adds its own deadline a few seconds past the import's limit.
- Canonical components/API contracts touched: `SqliteTaskQueueStore` (`enqueue`, `recordTaskProgress`, `latestByType`), `TaskQueueWorker` dispatch beside `runAppUpdateTask`, `scripts/import_instance.php` unchanged.

## Stage 2
**Estimate: 0.06**
- Goal: the page remembers each source after a queued import.
- Dependencies: Stage 1.
- Expected changes: `state/private/instance_import/sources.txt`, one `Source:` and `Last-Used:` pair per source, newest first, capped at 20 entries. Queuing an import adds or refreshes its source.
- Verification approach: unit test for add, refresh, cap and an unreadable file; a check that the path is outside the content repository.
- Estimate details: driver: small store; uncertainty: low.
- Risks or open questions: none beyond the Key Risks entry.
- Canonical components/API contracts touched: the project's `Key: value` record helpers (reused, no JSON).

## Stage 3
**Estimate: 0.14**
- Goal: Tools → Import exists and shows state before any controls.
- Dependencies: Stage 1.
- Expected changes: feature flag `instance_import_page`, off by default. Route `/tools/import/`, a new `ImportController` modeled on `DeployController`, and a `tools_import.php` template. The page shows the last import's result from its saved report, or a pending run, or a canceled run. Import joins the operator group in `ToolsPageSupport`, with icons in every set in `public/assets/theme-nav-icons.css`.
- Verification approach: route tests for root, non-root and flag-off views; `NavIconSetsTest`; `./v3 test --smoke` with those tests.
- Estimate details: driver: page wiring and icons in every set; uncertainty: picking icons that read clearly in pixel and smooth sets.
- Risks or open questions:
  - Impact: non-root viewers see operator data.
  - Early warning / validation: the non-root route test.
  - Mitigation: decide the controls and state on the server with `securityReviewAllowed`, as Deploy does.
- Canonical components/API contracts touched: `DeployController` pattern, `ToolsPageSupport::navOptions`, `tools_nav_card.php`, `FeatureFlagRegistry`, route list in `Application.php`.

## Stage 4
**Estimate: 0.14**
- Goal: the operator can start Import, Resume or Cancel and watch progress.
- Dependencies: Stages 1–3; PR #285 merged.
- Expected changes: `POST /tools/import/` with actions `status`, `import`, `resume` and `cancel`, a same-origin check and a root gate, JSON for the page script and a plain-form fallback. A page script modeled on `deploy_control.js` polls `status` and shows progress lines. Import is refused while a run is pending, queued or running. The source field offers remembered sources.
- Verification approach: controller tests for each action, refusal and the plain-form fallback. A browser check on phone and desktop widths.
- Estimate details: driver: polling script and states; uncertainty: low, since Deploy has the same shape.
- Risks or open questions: none new.
- Canonical components/API contracts touched: `SameOriginRequest`, `RouteServices::sendJson` and `requestData`, the `deploy_control.js` polling pattern.

## Stage 5
**Estimate: 0.08**
- Goal: the result is readable, with per-file details out of the way.
- Dependencies: Stage 4.
- Expected changes: the result card shows counts, image status and grouped review causes. An Advanced section, closed by default, lists per-file entries from the saved report.
- Verification approach: template test with a partial report; a check in each theme.
- Estimate details: driver: report fields to markup; uncertainty: report size on a big import.
- Risks or open questions:
  - Impact: a large report makes the page slow.
  - Early warning / validation: a test report with 10,000 entries.
  - Mitigation: cap the list and say how many are hidden.
- Canonical components/API contracts touched: the existing report fields from `ContentImportRunner::report`.

## Stage 6
**Estimate: 0.07**
- Goal: end-to-end check and documentation.
- Dependencies: Stages 1–5.
- Expected changes: a "Browser entry" section in `docs/runbooks/instance_content_import.md`, replacing "no web UI". A line in `docs/concepts/instance_content_import.md`. An entry in `docs/plans/ui_copy_audit_checklist.md`. The feedback item update.
- Verification approach: a loopback import, an interrupted import resumed, and one canceled, all from the page with a local worker. Phone and desktop in every theme, following `CONTRIBUTING.md`. Diff review for duplication and scope. Full suite not run.
- Estimate details: driver: theme checks; uncertainty: headless browser availability.
- Risks or open questions: the external check on zenmemes.com needs the maintainer to deploy and enable the flag.
- Canonical components/API contracts touched: none new.

## Study checkpoint

- Step 3 handoff: 0.65 projected total, up from 0.60 because the page now also offers Cancel. Scope v3 (Step 2 scope plus Cancel), baseline `c95e22b3`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified. Stage estimates are independent and not summed.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
