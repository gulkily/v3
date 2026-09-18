# Forte Activity Commits View — Step 1: Solution Assessment

## Problem
The Activity view has no way to browse commits themselves (only individual actions), and trimming an action's detail view to just its relevant files (record, signature, public key - dropping the rest) removes the only place left to see everything a commit touched, so a real "browse this commit" surface needs to exist somewhere.

## Option A: Derive commits from the existing `activity` table (`GROUP BY source_commit_sha`)
A "Commits" filter alongside today's 5 views, querying `activity` grouped by `source_commit_sha` (row count + min/max `created_at` per group) for the list; selecting a commit shows its full file list via the already-built `SqliteActivityCommitManifestCache`/`git diff-tree` path.
- Pros: no read-model schema change; a commit is just a different grouping of data already fully wired for pagination, sorting, and the manifest cache - this view can reuse nearly all of it as-is.
- Cons: a commit's "date" is inferred from its activity rows' `created_at` (min/max), not git's own commit date - normally identical in practice, but not strictly guaranteed.

## Option B: Read commits live from `git log`
A separate query path that shells out to `git log` against the content repository directly, independent of the `activity` table.
- Pros: authoritative commit metadata straight from git, zero drift risk.
- Cons: an entirely separate data/pagination/sort path from everything just built for Activity - none of the existing `fetchActivity()`/cursor/sort machinery applies; repeated `git log` calls for pagination are exactly the per-request git-shelling cost the manifest-cache fix just eliminated elsewhere.

## Option C: Persist a dedicated `commits` table during read-model rebuild
`ReadModelBuilder` populates a new table from `git log` once per rebuild; the view queries it like any other read-model table.
- Pros: fast and authoritative at read time, no per-request git cost.
- Cons: a real read-model schema change (new table, rebuild-pipeline changes) - directly against this process's "avoid database schema changes when possible" guidance, and the largest of the three to build/verify.

## Decision
**Option C.** Authoritative commit metadata straight from git, queried fast at read time with no per-request git cost, outweighs the schema-change cost this process would otherwise prefer to avoid. Per-action detail views get scoped to relevant files only (record + signature + public key, via the existing `sourceCommitFileRole()` classification); the full file list moves to this new Commits view, where browsing everything a commit touched is the whole point. Commit linking beyond today's existing link mechanism is explicitly deferred.
