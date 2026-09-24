# Forte Activity Commits View — Step 3: Development Plan

## Stage 1
- Goal: Persist commit metadata into a new `commits` table, populated during read-model rebuild from git's own history.
- Dependencies: none (first stage).
- Expected changes:
  - New `commits` table (conceptually: `sha` primary key, `author_name`, `author_email`, `committed_at`, `subject`, `file_count`).
  - New `indexCommits(PDO $pdo): void` stage in `ReadModelBuilder`, wired into the existing `rebuild()` pipeline alongside `indexActivity()`/`indexPosts()`/etc., populated from one `git log` invocation against the content repository (not one call per commit).
- Verification approach: Rebuild the read model against the local fixture repo and confirm the `commits` table's row count matches `git log --oneline | wc -l`; spot-check the known large import commit's row has the correct `subject` and `file_count` (1955).
- Risks or open questions:
  - Commit subjects can contain characters that collide with a naive text delimiter - parse `git log`'s output using a delimiter unlikely to appear in a message, not naive line-splitting.
- Canonical components/API contracts touched: `ReadModelBuilder`.

## Stage 2
- Goal: Query the `commits` table with the same keyset-cursor pagination and sort-column pattern already built for activity rows.
- Dependencies: Stage 1.
- Expected changes:
  - `fetchCommits(string $sortColumn, string $sortDirection, ?array $afterCursor = null): array` returning `{items, has_more}`, mirroring `fetchActivity()`'s shape and cursor semantics.
  - `countCommitsTotal(): int`, mirroring `countActivityViewTotal()` (no view-filtering needed, so simpler).
  - `resolveCommitSort(string $requestedColumn, string $requestedDirection): array{column, direction}`, mirroring `resolveActivitySort()`; `date` is the only sortable column for this stage (author/subject sorting is not required by Step 2 and can follow later if wanted).
- Verification approach: Reflection-invoke `fetchCommits()` directly (same approach used to verify `fetchActivity()` earlier) across two pages and confirm no gaps/duplicates at the page boundary.
- Risks or open questions: none beyond what Stage 1 already covers.
- Canonical components/API contracts touched: new `fetchCommits()`/`countCommitsTotal()`/`resolveCommitSort()` (`Application.php`), mirroring existing activity equivalents.

## Stage 3
- Goal: Commit rows appear in the list pane under a new "Commits" filter, reusing the existing per-row visibility-flag mechanism, without disturbing the other 5 views or their merged item pool.
- Dependencies: Stage 2.
- Expected changes:
  - `normalizeActivityView()`'s allowlist gains `commits`; `renderForteActivity()` gains a 6th view entry that fetches via `fetchCommits()`/`countCommitsTotal()` instead of `fetchActivity()`, producing rows flagged `view_commits=true` (all other `view_*` flags false) kept in their own parallel row set rather than merged into the existing `$itemsById` pool, since commit rows don't share the action-item shape.
  - New `paned_activity_commit_row.php` partial for rendering a commit row (reuses the existing row markup/CSS conventions, not the item-specific fields).
  - `paned_activity_filter_list.php` shows "Commits" as a 6th option (no fork - same partial, one more entry).
  - `/api/forte_activity_page` extended to branch on `view=commits`, delegating to `fetchCommits()` and the commit row partial, so "Load more" works the same way it already does for the other 5 views.
- Verification approach: Load the Activity page, switch to "Commits", confirm the row count matches the persisted table's total, no commit rows leak into the other 5 filters, and "Load more" pages correctly.
- Risks or open questions:
  - Keeping commit rows structurally separate from `$itemsById` means the merge/sort logic used for the other 5 views must not assume every row shares one item shape - worth double-checking nothing iterates "all rows" generically in a way that would choke on a commit row.
- Canonical components/API contracts touched: `renderForteActivity()`, `paned_activity_filter_list.php`, `/api/forte_activity_page`; new `paned_activity_commit_row.php`.

## Stage 4
- Goal: Selecting a commit row fetches and shows that commit's full file manifest on demand, without pre-rendering every loaded commit's (potentially large) manifest up front.
- Dependencies: Stage 3.
- Expected changes:
  - New endpoint (e.g. `GET /api/forte_commit_detail?sha=...`) reusing `SqliteActivityCommitManifestCache`/`activity_commit_manifest.php` to return one commit's manifest HTML.
  - `paned_activity_reader.js`: selecting a commit row triggers a fetch to this endpoint and injects the result into the detail pane for that row only; a small per-sha client-side cache avoids re-fetching a commit already viewed this session.
- Verification approach: Select several different commits, including the known 1955-file one, and confirm the correct, distinct manifest renders each time with no cross-contamination; confirm re-selecting an already-viewed commit doesn't re-fetch.
- Risks or open questions:
  - Needs a brief "Loading…" affordance while the fetch is in flight, mirroring the existing Load More button's pattern, so selecting a large commit doesn't look unresponsive.
- Canonical components/API contracts touched: new `/api/forte_commit_detail` endpoint reusing `activity_commit_manifest.php`/`SqliteActivityCommitManifestCache`; `paned_activity_reader.js`.

## Stage 5
- Goal: An action's own detail view shows only the files in its provenance chain (record(s), signature, public key) instead of the whole commit.
- Dependencies: none beyond existing infrastructure (independent of Stages 1-4, grouped into this feature for a coherent whole).
- Expected changes:
  - New `activityItemRelevantFiles(array $item): array`, deriving the narrow subset from the already-computed (and cached) full commit manifest by matching the item's own `source_path` plus its signature/key pair - and, specifically for `identity_bootstrap`, also the paired identity record established by that same action.
  - `paned_activity_detail_article.php` renders this narrow list inline (small enough - at most 4 entries - to safely pre-render for every loaded action, unlike the full manifest).
- Verification approach: Select a regression-test action that shares the 1955-file commit and confirm its detail view shows only its own relevant files; select an `identity_bootstrap` action and confirm its detail view includes the paired identity record (4 files total, matching Step 2's confirmed exception).
- Risks or open questions:
  - The "this item's own signature/key" matching must stay consistent with how `activityCommitSignatureMetadata()` already resolves signer/key today, so the narrow list and a commit's full manifest (Stage 4) never disagree about which file is whose.
- Canonical components/API contracts touched: `paned_activity_detail_article.php`; new `activityItemRelevantFiles()` (`Application.php`), built from the existing manifest/role-classification helpers.

## Stage 6
- Goal: Remove the shared-manifest-block/move mechanism built specifically to dedupe full-manifest rendering across many actions sharing one commit - no longer needed now that action detail views never embed a full manifest and commits have their own dedicated, on-demand view.
- Dependencies: Stage 4 (commits have their own full-manifest path), Stage 5 (actions no longer need one).
- Expected changes:
  - Remove `showCommitManifestFor()`/`commitManifestBlocks` tracking and the shared-block dedup branch from `mergeAppendedDetailArticles()`/`selectItem()` in `paned_activity_reader.js`.
  - Remove the now-dead `$commitManifestsBySha` block-rendering loop from `paned_activity_detail_pane.php`.
- Verification approach: Confirm action selection still works correctly across all 5 non-commit views after the cleanup; confirm a full page load's response size drops back to roughly pre-shared-block levels, since per-item content is small again.
- Risks or open questions: none beyond re-running the existing manual selection/Load-More checks to confirm nothing broke.
- Canonical components/API contracts touched: `paned_activity_reader.js`, `paned_activity_detail_pane.php`.
