# Thread Archive v3 Command Slices

## Goal

As an operator, archive an entire thread to a zip file, including its component canonical files, and remove that thread from the public site. Re-import should be anticipated through archive structure and metadata, but import is out of scope.

## Scope

- Add a `v3` command only; no public UI and no HTTP endpoint.
- Archive repository-relative canonical files into a zip.
- Remove archived canonical files from the live repository after the zip is verified.
- Refresh derived read-model and static public artifacts so the archived thread is no longer visible.
- Preserve a manifest that can support future re-import.

## Slices

### Slice 0: Save Plan

Status: completed

- Save this slice plan to `docs/plans/thread_archive_v3_command_slices_v1.md`.
- Commit the plan before implementation starts.

### Slice 1: CLI Contract And Archive Skeleton

Status: completed

- Add `./v3 archive-thread <thread_id> [repository_root] [database_path] [artifact_root] [archive_path]`.
- Create `scripts/archive_thread.php`.
- Resolve default paths consistently with existing `v3` commands.
- Validate basic inputs and print operator-readable output.

### Slice 2: Component Discovery And Zip Manifest

Status: pending

- Resolve the thread root post and reject missing threads or reply ids.
- Discover replies, thread-label records, and post-reaction records tied to the thread.
- Write a zip using repository-relative paths.
- Include `manifest.json` with thread id, timestamp, source commit, included paths, and SHA-256 checksums.

### Slice 3: Remove From Live Repository And Commit

Status: pending

- After successful zip creation and verification, delete archived canonical files from the live repository.
- If the repository is a git worktree, commit the deletions with `Archive thread <thread_id>`.
- Keep the archive file outside the canonical repository by default.
- Run under the existing execution lock pattern.

### Slice 4: Refresh Derived Public State

Status: pending

- Rebuild the read model from remaining canonical records.
- Build static artifacts from the rebuilt read model.
- Remove stale per-thread and per-post artifacts for the archived records before or after rebuild.
- Report archive path, archived files, removed files, and refresh results.

### Slice 5: Tests And Documentation Polish

Status: pending

- Add smoke coverage for zip contents and manifest.
- Cover removal from live canonical records.
- Cover read-model/static artifact refresh enough to prove the thread is no longer public.
- Cover failure cases for missing thread ids and reply ids.
- Update command usage text.
