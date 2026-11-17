# Import cancel one-shot record

Cycle: `v3/import_cancel`; Mode: one-shot; Record/index: [this record](./import_cancel_one_shot.md), [plans index](../README.md)
Debug mode: enabled ([debug log](./import_cancel_debug_log.md))
Effort study: `railspike-effort` / `v3/import_cancel`; [host ledger](../effort_estimation_trial.md)
Extended estimates: disabled (single work unit)

**Cycle estimate: 0.25**

Status: locally complete; next permitted action: push the branch, open the PR and reply in the thread.

Original request: "approved step 2. as one-shot, as a pre-requisite, please add a command to cancel a partial import instead of resuming it (resume should still be available)". Thread: https://zenmemes.com/threads/thread-20261113042243-1cbe8b8a. Feedback: PF-20261116-F01. Parent cycle: `v3/import_instance_gui` (staged, at Step 3).

Authorization: maintainer in session, 2026-11-16: one-shot execution of the cancel command, plus branch, PR and thread reply. Not merging or deploying.

Outcome and work type: application change. `./v3 import-instance --cancel` discards a pending interrupted import; `--resume` is unchanged.

Approach and risks:
- Cancel is allowed only before the import commits its content. After that, the records are in Git history, and undoing them would mean a revert commit or retraction, which this request did not ask for. Cancel then refuses and points to `--resume`, which only finishes publication.
- Cancel removes only import-owned files that are untracked in HEAD and still match the import's bytes, and unstages them. A changed file is kept and listed, following the recovery rule that divergent edits are preserved.
- Other writes may commit after the interruption. Cancel decides "committed" by the import's own commit subject, not by a moved HEAD, so unrelated commits don't block it.
- The journal moves to `canceled.json` in the run's saved directory, beside the saved copies, so the run stays reviewable. It stays JSON because it is the existing internal recovery format, not a human-facing record.
- Cancel takes the same two locks as import and resume.

Reuse decisions: extends `ContentImportRunner` (`src/ForumRewrite/Import/ContentImportRunner.php`) with `cancel()`, reusing its locks, journal path, `assertSafeParents`, `git` and `save` helpers. The CLI flag sits beside `--resume` in `scripts/import_instance.php`. Tests extend `ContentImportRecoveryTest` and `ImportInstanceCommandTest` with their existing workspace helpers.

Completion conditions: an operator runs `./v3 import-instance --cancel` with the destination options; installed files are removed, saved copies remain, and a new import can start. Recovery: a refused cancel names `--resume`; a missing journal says there is nothing to cancel. Release: merged and deployed; the GUI cycle then builds on it.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md), used unchanged; the runbook and CLI reference gain the cancel path.

Verification and recovery plan: runner tests interrupt at `copied` and `staged`, cancel, check the tree is clean and a new import completes; a changed file is kept; a committed import refuses cancel and still resumes. A CLI test covers refusal, flag conflicts, resume after refusal and the no-journal message. No UI change.

Starting revisions and branch: `c95e22b3`; `feat/import_cancel`.
Planning commit: none; one-shot completion commit, hash pending.

Dated execution history:
- 2026-11-16: implemented runner `cancel()`, CLI `--cancel`, usage text, runbook and CLI reference; recovery messages now mention `--cancel`.

Study history: intake 0.25 after reading the runner (no pristine intake); closeout retrospective 0.25, scope unchanged; Claude single exposed rater; R1 hashes not re-verified. Maintainer acceptance pending.

Outcome: delivered as scoped. Not covered: a CLI-level replay of a successful cancel, since the CLI cannot easily be interrupted before its commit; the runner tests cover that path.

Verification: `./v3 test --smoke ContentImportRecoveryTest ImportInstanceCommandTest::testCliCancelRefusesCommittedImportAndReportsMissingJournal ImportInstanceCommandTest::testCliResumeDoesNotNeedTheRemoteSourceAgain`: passed (smoke set included). Full suite not run.

Final review: diff limited to the runner, CLI script, `v3` usage, runbook, CLI reference, tests, this record, the feedback item, the plans index and the ledger.

Commit/merge/publication status: committed on branch; PR pending; not merged or deployed.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
