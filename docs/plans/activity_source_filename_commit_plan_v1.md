# Activity Source Filename And Commit Plan

## Feasibility
Yes. Activity rows already come from canonical repository records, so each item can be given a deterministic source filename and, when the canonical repository is a git checkout, the commit SHA that last introduced or changed that source file.

The implementation should store this metadata in the read model during full rebuilds and incremental updates. The activity page should not run git commands per request.

## Current State
- `/activity/` renders `templates/pages/activity.php` with items from `Application::fetchActivity()`.
- Activity rows are stored in the SQLite `activity` table.
- Full read-model rebuild inserts activity in `ReadModelBuilder::indexActivity()`.
- Incremental post writes insert activity in `IncrementalReadModelUpdater::insertActivity()`.
- Incremental thread-label writes rebuild label activity in `IncrementalReadModelUpdater::refreshThreadLabelActivity()`.
- Post records have deterministic paths through `CanonicalPathResolver::post($postId)`.
- Thread-label activity currently loses the source `ThreadLabelRecord::recordId` before insertion, so it cannot yet derive `records/thread-labels/<record-id>.txt` at render time.

## Proposed Data Contract
Add nullable columns to `activity`:

- `source_path TEXT NULL`: repository-relative canonical filename, for example `records/posts/root-001.txt`.
- `source_commit_sha TEXT NULL`: full git commit SHA for the source file, or `no-git`/`git-error` only if the repository cannot provide a value.

For first implementation:
- thread and reply items use `records/posts/<post-id>.txt`;
- identity/bootstrap/approval items are still post activity rows, so they use the same post path;
- thread label items use `records/thread-labels/<record-id>.txt` after preserving the label record id in the activity event model.

## Implementation Plan

### Stage 1: Read-model schema and source path plumbing
- Bump `ReadModelMetadata::SCHEMA_VERSION`.
- Add `source_path` and `source_commit_sha` to the `activity` table in `ReadModelBuilder`.
- Extend `ReadModelBuilder::indexPosts()` to keep each post's `source_path` from the scanned relative path.
- Extend thread-label activity event arrays in `ReadModelBuilder::indexThreadLabels()` to include `source_path` using `CanonicalPathResolver::threadLabel($record->recordId)`.
- Extend matching PHPDoc array shapes so the source metadata is explicit.

### Stage 2: Commit SHA resolution
- Add a small helper for read-model indexing, likely in `ReadModelBuilder` first:
  - input: repository-relative path;
  - output: `git log -1 --format=%H -- <path>` result;
  - fallback: `no-git` when `.git` is absent, `git-error` when git fails, or `null` if the path has no history.
- Use the helper while inserting full-rebuild activity rows.
- Keep this out of `Application::fetchActivity()` and templates.
- Consider caching path-to-SHA results during a rebuild so duplicate path lookups do not repeat git commands.

### Stage 3: Incremental update support
- In `IncrementalReadModelUpdater::insertActivity()`, set:
  - `source_path` to `CanonicalPathResolver::post($record->postId)`;
  - `source_commit_sha` to the `$commitSha` passed to the current write update.
- In `computeThreadLabelState()`, include the thread-label `record_id` or `source_path` in each activity event.
- In `refreshThreadLabelActivity()`, set `source_path` to `CanonicalPathResolver::threadLabel($recordId)` and `source_commit_sha` to the `$commitSha` from `applyThreadLabelWrite()`.
- Update the method signatures/PHPDocs as needed so the commit SHA can be passed into the refresh method.

### Stage 4: Activity rendering
- Select `activity.source_path` and `activity.source_commit_sha` in `Application::fetchActivity()`.
- Include them in the mapped item array.
- Render a compact metadata line in `templates/pages/activity.php`, for example:
  - `Source: records/posts/root-001.txt @ abc1234`
- Display a shortened hash in the UI, but keep the full hash available in a `title` attribute or equivalent text if existing style allows.
- Omit the line when both values are empty/null.
- Decide whether RSS descriptions should include the source line. My recommendation is to include it only in HTML for the first slice, then add RSS if needed.

### Stage 5: Tests and verification
- Update `LocalAppSmokeTest` activity assertions to expect source filename text for a post item and a thread-label item.
- Add or extend a test with a real git-backed temp repository so at least one activity row has a real commit SHA.
- Add no-git coverage using the existing fixture path expectations, since local smoke tests currently expect `no-git` for app version.
- Run:
  - `php -l src/ForumRewrite/ReadModel/ReadModelMetadata.php`
  - `php -l src/ForumRewrite/ReadModel/ReadModelBuilder.php`
  - `php -l src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l templates/pages/activity.php`
  - `php tests/run.php`

## Risks And Decisions
- Full rebuild git lookups may be slow on large repositories if done one path at a time. Cache results at minimum; batch lookup can be considered later if profiling shows a problem.
- `source_commit_sha` needs a clear meaning. This plan uses the latest commit touching the source file for rebuilds, and the write commit SHA for incremental writes.
- Thread-label activity needs extra source metadata before insertion. Without preserving `recordId`, label items can only point to the thread, not to the canonical label record.
- In no-git fixture environments, the UI should avoid presenting `no-git` as a real commit hash. A label like `commit unavailable` is preferable if the value is `no-git`, `git-error`, or null.

## Approval Gate
Pause here until this plan is approved. After approval, implement in slices with schema/read-model changes first, then rendering and tests.
