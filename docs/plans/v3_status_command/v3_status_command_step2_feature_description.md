# `v3` Status Command — Step 2 Feature Description

## Problem

Operators currently have to combine the read-model status API, task-queue status, and lock symptoms to understand whether the system needs attention. `./v3 status` should provide one read-only local summary, including the authoritative state of queued-worker rebuilds.

## User Stories

- As an operator, I want to run one local status command so that I can assess repository, read-model, and queue health quickly.
- As an operator, I want to see whether a rebuild task is queued or running so that I know whether to wait, run recovery, or investigate a failed task.
- As an operator, I want a held shared lock labelled accurately so that I do not mistake general write activity for a rebuild.
- As an operator, I want clear next actions for stale or unavailable derived state so that I can recover without editing private runtime data.

## Core Requirements

- `./v3 status` is a read-only, human-readable operator command that uses the same project-path defaults as existing local commands.
- It reports read-model readiness, freshness, stale-marker state, and shared-lock state using the existing health semantics.
- It distinguishes a queued-worker read-model rebuild as queued, running, failed, or absent; a held shared lock is reported separately as general protected activity.
- It summarizes task-queue availability and counts, while directing detailed task inspection to `./v3 task-queue status` and detailed Fastmod inspection to `./v3 fast-score status`.
- Missing or unreadable optional runtime state is identified clearly without changing state or hiding the remaining available status data.

## Shared Component Inventory

- `./v3` dispatcher: extend as the canonical entry point for the new operator command.
- `/api/read_model_status` and Tools → System State: reuse their canonical read-model freshness, stale-marker, and shared-lock semantics; do not create competing health definitions.
- `./v3 task-queue status`: reuse its task-queue state as the detailed queue surface; the new command supplies only the concise summary.
- `./v3 fast-score status`: retain as the detailed Fastmod surface; do not duplicate its work and scoring detail in the initial command.
- Operator recovery runbook and CLI reference: extend as the canonical documentation for interpreting status and choosing a recovery command.

## Simple User Flow

1. An operator runs `./v3 status`.
2. The command prints read-model health, lock state, rebuild-task state, and queue summary.
3. The operator follows the printed next action or opens the relevant detailed status command.
4. The operator re-runs `./v3 status` to confirm recovery.

## Success Criteria

- One command shows whether the read model is ready, stale, unavailable, or protected by an active shared lock.
- A task-queue rebuild is visibly distinguished as queued, running, failed, or absent.
- A held lock is never presented as proof that a manual rebuild is in progress.
- The command makes no writes, enqueues no work, and remains useful when the task queue or read model is unavailable.
- Existing web and detailed CLI status surfaces retain their current behavior.

Reply **Approved Step 2** to proceed to the development plan.
