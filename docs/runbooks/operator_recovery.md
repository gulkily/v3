# Operator Recovery Runbook

This runbook describes how to inspect and recover the PHP forum rewrite in production.

## Primary Status Surface

Use:

```text
./v3 status

# Remote or web-only deployments:
GET /api/read_model_status
```

`./v3 status` is read-only and summarizes the read model, shared lock, task
queue, and current queued-worker read-model rebuild. Use its printed next
action first, then open the detailed command it identifies.

Important status values:

- Read model: `ready`, `stale`, or `unavailable`
- Read-model rebuild task: `queued`, `running`, `failed`, or `absent`
- Shared lock: `locked` or `unlocked`. A lock means general protected
  activity, not proof that a manual rebuild is running.
- Task queue: availability and queued/running/failed counts. Use
  `./v3 task-queue status` for task IDs, attempts, and failure codes.

The API retains the same underlying data in key/value form, including
`rebuild_task_status`, `task_queue_status`, and task-queue counts.

## Normal Recovery Command

The deterministic recovery command is:

```bash
php scripts/rebuild_read_model.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH"
```

If production serves static releases:

```bash
php scripts/build_static_artifacts.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH" "$FORUM_STATIC_HTML_ROOT"
```

## Common Cases

### 1. Read Model Is Stale

Symptoms:

- `./v3 status` reports `Read model: stale` or `Read model: unavailable`
- `stale_marker=present`
- read routes may show recovery/configuration failures

Action:

1. run `./v3 status`
2. note `stale_reason` and `stale_commit_sha`
3. run a manual rebuild
4. publish a fresh static release if production uses static HTML
5. re-check `/api/read_model_status`

When the internal task queue is configured, an operator can request the same rebuild for cron processing instead of running it inline:

```bash
./v3 task-queue enqueue-rebuild
./v3 task-queue status
```

The configured cron worker runs the queue. A failed task remains visible in the status output; investigate the logged failure and use the manual rebuild command when immediate recovery is required.

### 2. Lock Contention

Symptoms:

- `./v3 status` reports `Shared lock: locked`
- rebuilds or writes appear blocked

Action:

1. wait briefly and retry the status endpoint
2. check whether another write or queued-worker rebuild is in progress; a
   lock alone does not identify which operation owns it
3. if the lock remains stuck after the PHP process is gone, inspect the host/process state
4. only remove stale lock files after confirming no active process is still using them

### 3. Git Write Failure

Symptoms:

- write routes return git-related errors
- no new commit is created

Likely causes:

- repository path is not a git checkout
- repository permissions are incorrect
- git user/write access is broken

Action:

1. confirm `FORUM_REPOSITORY_ROOT` points to the intended writable checkout
2. confirm `.git/` exists
3. confirm the web user can write there
4. confirm non-interactive `git status` and `git rev-parse HEAD` work as the deploy user

### 4. Post-Commit Refresh Failure

Symptoms:

- a write reports success through commit creation but says derived state was marked stale
- `stale_marker=present`

Action:

1. do not attempt to rewrite the canonical content again
2. run `./v3 status`
3. run the manual rebuild command
4. rebuild artifacts if needed
5. confirm the stale marker clears

## What Must Be Backed Up

Canonical and should be backed up:

- the writable repository checkout
- deployment configuration
- Apache site configuration

Derived and rebuildable:

- SQLite read-model database
- lock file
- stale marker
- static HTML release directories under `FORUM_STATIC_HTML_ROOT`

## Safe Recovery Principle

Prefer:

- preserve canonical repo state
- rebuild derived state

Avoid:

- manual edits to derived SQLite state
- deleting canonical records to fix derived-state issues

## Useful Commands

```bash
git -C "$FORUM_REPOSITORY_ROOT" rev-parse HEAD
git -C "$FORUM_REPOSITORY_ROOT" status --short
php scripts/rebuild_read_model.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH"
php scripts/build_static_artifacts.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH" "$FORUM_STATIC_HTML_ROOT"
./v3 task-queue status
./v3 task-queue enqueue-rebuild
./v3 task-queue enqueue-fast-score
./v3 task-queue run --limit=1 --score-limit=25
./v3 status
```
