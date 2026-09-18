# Forte Activity Commits View — Step 4: Implementation Summary

## Stage 1 - `commits` table populated from git log during read-model rebuild
- Changes:
  - `ReadModelBuilder::createSchema()`: new `commits` table (`id` autoincrement PK, `sha` unique, `author_name`, `author_email`, `committed_at`, `subject`, `file_count`) plus `commits_committed_at_idx` for the pagination/sort access pattern Stage 2 will use; `dropSchema()` drops it alongside the other tables on rebuild.
  - New `indexCommits(PDO $pdo): void`, wired into `rebuild()`'s existing pipeline (`measure('index_commits', ...)`) alongside `index_activity`/etc. One `git log --format=... --shortstat` invocation (not one call per commit) produces every commit's metadata plus its file count in a single pass; fields are split on `\x1f` (unit separator - can't appear in a commit subject), and a commit with no `--shortstat` line (e.g. an empty/merge commit) defaults to `file_count: 0` rather than being skipped.
- Verification:
  - `php -l` - no syntax errors.
  - Ran `php scripts/rebuild_read_model.php` against the local fixture repo and queried the resulting `commits` table directly: **211 rows**, exactly matching `git -C state/local_repository log --oneline | wc -l`.
  - Spot-checked the known large "Import repository archive" commit's row: `subject` and `file_count` (**1955**) both match the ground truth established earlier in this session (`git show --stat` on that sha).
  - Confirmed schema via `PRAGMA table_info(commits)` and `sqlite_master` - all 7 columns and the `commits_committed_at_idx` index present as designed.
- Notes:
  - While verifying this stage, discovered the local fixture's content repository (`state/local_repository`) had accumulated 3,712 loose git objects with no packfile (from this long session's own testing), making the *existing*, unrelated per-file `sourceCommitShaForPath()` lookups (used by `indexPosts()`/`indexThreadLabels()`/etc., already present before this feature) take ~111ms each - across ~2,300 record files, that alone stretched a full rebuild from ~5s to ~144s. Ran `git gc` on the content repo (routine, non-destructive maintenance - packs loose objects, doesn't discard anything reachable) to restore normal performance; full rebuild is back to ~14s. This wasn't caused by `indexCommits()` and isn't part of this feature's scope, but was necessary to get a clean read while verifying it.
  - An initial read of the constructed `git log` command's printed output looked like the `\x1f` delimiter had been stripped by `escapeshellarg()` - turned out to be a false alarm: it's a non-printing control character, so it's simply invisible when echoed to a terminal, not actually absent from the string. The real rebuild's correctly-parsed 211 rows confirm the delimiter is intact and parsing works as designed.

## Stage 2 - Sort-aware fetch/count for commits
- Changes:
  - `fetchCommits(string $sortColumn, string $sortDirection, ?array $afterCursor = null): array` (`Application.php`) - mirrors `fetchActivity()`'s keyset-cursor pagination shape over the `commits` table; simpler than `fetchActivity()` since a commit has no "view" to filter by, so the only `WHERE` fragment is the cursor itself.
  - `countCommitsTotal(): int` - mirrors `countActivityViewTotal()`'s purpose for the left-pane folder count; every commit counts, no per-view filter.
  - `resolveCommitSort()`/`commitSortSql()`/`commitSortValueFromItem()` - mirror `resolveActivitySort()`/`activitySortSql()`/`activitySortValueFromItem()`'s shapes; only `date` is sortable for now per the Step 3 plan's own scope decision, kept as separate small resolvers (not folded into the activity versions) since the two views' valid column sets are unrelated.
  - Reuses `ACTIVITY_ITEM_LIMIT` as the page size rather than introducing a new constant for the same 100-row page size.
- Verification:
  - `php -l` - no syntax errors.
  - Reflection-invoked `fetchCommits()` directly, paging through the full 211-row `commits` table with the real cursor: 100 + 100 + 11 items across 3 pages, `has_more` correctly true/true/false, zero duplicate ids, zero order violations, and the total unique ids collected (211) exactly matches `countCommitsTotal()`.
- Notes:
  - Same reflection-based verification approach used for `fetchActivity()` in the earlier sortable-columns feature, applied here for consistency.
