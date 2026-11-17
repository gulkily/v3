# Worker before the read model one-shot record

Cycle: `v3/worker_before_read_model`; Mode: one-shot; Record/index: [this record](./worker_before_read_model_one_shot.md), [plans index](../README.md)
Debug mode: enabled (no findings beyond the parent cycle's log)
Effort study: `railspike-effort` / `v3/worker_before_read_model`; [host ledger](../effort_estimation_trial.md)
Extended estimates: disabled (single work unit)

**Cycle estimate: 0.05**

Status: locally complete; next permitted action: push, open the PR, reply in the thread.

Original request: "please add a fix for the "unable to open database file" error" (maintainer in session, 2026-11-17 UTC). The error was noted in [`v3/import_without_worker`](../import_without_worker/import_without_worker_step4_implementation_summary.md) Stage 5. Feedback: PF-20261117-B01.

Authorization: maintainer in session; one-shot through PR and thread reply. Not merging or deploying.

Outcome and work type: application change. A worker run before the first page request completes cleanly.

Approach and risks: `scripts/task_queue.php run` now skips two discovery steps until there is something to discover:
- retraction discovery, until the repository has `.git`;
- agent-reply reconciliation, until the read-model file exists.

Reproduction on the base, with a temporary empty state directory: the run exits 1. It logs `retraction_discovery_failed` and `run_error … unable to open database file` (`reconcileAgentReplyTasks` opens the missing read model; the missing repository fails retraction discovery).

Risk: a worker reporting a fresh heartbeat before the read model exists might turn the first page request into "rebuild queued". Checked on a fresh worktree with `./v3 start`: the heartbeat was fresh, and the first `/threads/` request still built the read model and returned the Board in 0.2 s. No errors were logged.

Reuse decisions: guards in the existing worker script. No new component.

Completion conditions: the first worker run on a fresh `./v3 start` is clean; queued tasks still run as before.

Concepts touched: [Internal task queue](../../concepts/internal_task_queue.md), used unchanged.

Verification:
- New `TaskQueueCommandTest::testWorkerOnAFreshInstanceRunsCleanlyBeforeTheFirstPageRequest`: exit 0, no error log, no repository or read model created, heartbeat fresh.
- `./v3 test --smoke TaskQueueCommandTest DevServerWorkerTest`: 491 passed (smoke set included).
- `./v3 test --smoke WriteApiSmokeTest TaskQueueWorkerTest LocalDevelopmentImportTest`: 707 passed.
- The fresh-worktree `./v3 start` check described above.
- Full suite not run.

Starting revisions and branch: `96462cfd`; `fix/worker_before_read_model`.

Study history: intake 0.05 after reproducing (no pristine intake); closeout retrospective 0.06; Claude single exposed rater; R1 hashes not re-verified. Maintainer acceptance pending.

Commit/merge/publication status: committed on branch; PR pending.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
