# Operator Recovery Runbook

This runbook describes how to inspect and recover the PHP forum rewrite in production.

## Primary Status Surface

Use:

```text
GET /api/read_model_status
```

Important fields:

- `status=ready|stale`
- `repository_head`
- `current_repository_head`
- `rebuilt_at`
- `lock_status`
- `stale_marker`
- `stale_reason`
- `stale_commit_sha`
- `rebuild_reason`

## Normal Recovery Command

The deterministic recovery command is:

```bash
php scripts/rebuild_read_model.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH"
```

If production uses sibling artifacts:

```bash
php scripts/build_static_artifacts.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH" "$FORUM_PUBLIC_ARTIFACT_ROOT"
```

## Common Cases

### 1. Read Model Is Stale

Symptoms:

- `/api/read_model_status` reports `status=stale`
- `stale_marker=present`
- read routes may show recovery/configuration failures

Action:

1. inspect `/api/read_model_status`
2. note `stale_reason` and `stale_commit_sha`
3. run a manual rebuild
4. rebuild artifacts if production uses sibling `public/*.html`
5. re-check `/api/read_model_status`

### 2. Lock Contention

Symptoms:

- `lock_status=locked`
- rebuilds or writes appear blocked

Action:

1. wait briefly and retry the status endpoint
2. check whether another write or rebuild is in progress
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
2. inspect `/api/read_model_status`
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
- sibling `public/*.html` artifacts

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
php scripts/build_static_artifacts.php "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH" "$FORUM_PUBLIC_ARTIFACT_ROOT"
```
