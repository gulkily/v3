# `v3` Status Command — Step 3 Development Plan

## Stage 1

- Goal: Provide one shared, read-only operator-status snapshot for the CLI and existing web status surfaces.
- Dependencies: Existing read-model metadata, stale marker, shared lock, and task-queue state; approved Step 2 requirements.
- Expected changes: Introduce a shared status collector (planned signature: `collect(): array`) that preserves the current read-model health semantics and adds the latest read-model rebuild task state (`queued`, `running`, `failed`, or `absent`). It must inspect a missing queue database without initializing it.
- Verification approach: Cover ready, stale, unavailable, locked, missing-queue, and each rebuild-task state with focused tests; assert a missing queue path is still absent after collection.
- Risks or open questions:
  - A shared lock identifies protected activity, not a manual rebuild; retain that distinction in the returned status.
  - A damaged or unreadable queue must yield a clearly scoped unavailable state while preserving other status fields.
- Canonical components/API contracts touched: `CodebaseStateController`, `/api/read_model_status`, Tools → System State, `SqliteTaskQueueStore`, `ExecutionLock`, read-model metadata, and stale-marker contracts.

## Stage 2

- Goal: Expose the snapshot as a concise, read-only `./v3 status` command.
- Dependencies: Stage 1 shared snapshot and existing `./v3` command-dispatch conventions.
- Expected changes: Add a dispatcher route and status command script that render read-model health, lock state, rebuild-task state, queue summary, and actionable links to `./v3 task-queue status` and `./v3 fast-score status`. Apply the established unknown-option contract and retain existing project-path defaults.
- Verification approach: Exercise dispatch, normal output, partial output when optional state is unavailable, and unknown-option help; verify the command neither writes runtime files nor enqueues tasks.
- Risks or open questions:
  - Output must make degraded state actionable without treating it as a command invocation failure.
  - Keep the concise summary separate from detailed task and Fastmod reports.
- Canonical components/API contracts touched: `v3` dispatcher, status command CLI contract, `TaskQueueDatabaseConfig`, shared operator-status snapshot, `./v3 task-queue status`, and `./v3 fast-score status`.

## Stage 3

- Goal: Document the operator contract and protect it against regressions.
- Dependencies: Stages 1 and 2 command output and test coverage.
- Expected changes: Add CLI reference and operator-recovery guidance for interpreting ready, stale, unavailable, lock, and rebuild-task states, including the limitation for manual rebuild detection.
- Verification approach: Run the focused command and status tests, then the relevant existing CLI/read-model test suite; manually confirm the documented next actions match the command output.
- Risks or open questions:
  - Documentation must not imply that a lock proves a rebuild is running.
- Canonical components/API contracts touched: `docs/reference/v3_cli.md`, `docs/runbooks/operator_recovery.md`, status-command tests, and the public operator-status output contract.
