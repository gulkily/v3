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

## Stage 3 - Wire "Commits" as a 6th filter
- Changes:
  - `normalizeActivityView()`'s allowlist gains `commits`.
  - `renderForteActivity()`: fetches commits via `fetchCommits()`/`countCommitsTotal()` as a parallel row set (`$commitItems`), kept out of `$itemsById`/`$items` entirely rather than merged - commits don't share the activity-item shape. Adds a `commits` entry to both `$viewPagination` and `$viewCounts`; `$selectedItemId` is forced to `''` when `$selectedView === 'commits'` (no `$viewItemIds['commits']` to look up against - commit selection is Stage 4's job, not pre-selected here).
  - New `templates/partials/paned_activity_commit_row.php` - mirrors `paned_activity_item_row.php`'s markup/CSS classes so it fits the existing list styling with no new CSS; maps short-sha/subject/committed_at onto the same Kind/Label/Date column positions. Uses `data-paned-activity-id="commit-<sha>"` (not the commit's own numeric `id`) since activity items and commits are both autoincrement ids from separate tables and could otherwise collide; a separate `data-paned-activity-commit-sha` attribute carries the raw sha for Stage 4.
  - `paned_activity_item_list.php` loops `$commitItems` after `$items`, rendering each via the new partial; visibility is `$selectedView === 'commits'` directly (commit items carry no `view_*` flags the way activity items do, since the rule is the same for every commit row). The existing Load More button loop already iterates `$viewPagination` generically, so it required no changes to pick up the new `commits` entry.
  - `paned_activity_filter_list.php` required no changes at all - it already loops `$viewCounts` generically, so the new `commits` entry just appears.
  - `forte_activity.php` threads `$commitItems` through to the list partial.
  - `handleForteActivityPage()` (`/api/forte_activity_page`): cursor decoding (identical shape for both) now happens once up front, then branches on `view === 'commits'` to `fetchCommits()` + the new row partial with an early return, leaving the existing activity-item path (rows + detail articles + shared manifest blocks) completely untouched below it. Commit pages always return `detail_html: ''` - Stage 4 fetches a commit's manifest on demand, not eagerly per row.
- Verification:
  - `php -l` on all changed PHP/template files - no syntax errors.
  - Default page load: "Commits" filter appears with folder count **211** (matches `countCommitsTotal()`/Stage 1's row count exactly).
  - `?view=commits`: page 1 has exactly 100 commit rows; a sample row (the known large commit) renders correctly - `ff8f35ee366b` (short sha) / `Import repository archive repository.tar.gz` (subject) / properly time-formatted date.
  - Paged through the full 211-commit set via `/api/forte_activity_page?view=commits` end to end: page 1 (100, `has_more=true`) -> page 2 (100, `has_more=true`) -> page 3 (11, `has_more=false`) - the known large commit correctly appears only on the last page (it's an old commit, so under default newest-first order it sorts near the end), confirming both the row count and the sort order are correct.
  - Confirmed commit rows carry `hidden` when the default view (`all`) is selected, and are absent from visibility (not merged into) the other 5 views' own row sets.
  - Regression: `/api/forte_activity_page?view=all` (existing Load More) still returns 100 rows correctly; classic `/activity/`, RSS (still exactly 100 items), and `/backup/` all unaffected. Server log clean throughout.
- Notes:
  - Selecting a commit row currently does nothing visible yet (no matching detail element exists) - this is the expected, temporary gap Stage 4 closes; Stage 3's own scope is the list/pagination wiring only, not detail rendering.
  - Per the Step 3 plan's own scope decision, clicking the Kind/Label sort headers while browsing Commits has no effect on commit order (`resolveCommitSort()` only recognizes `date`, falling back to it for anything else) - deliberate, not a bug.
