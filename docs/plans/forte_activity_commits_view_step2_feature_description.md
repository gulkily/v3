# Forte Activity Commits View — Step 2: Feature Description

## Problem
The Activity view has no way to browse commits themselves, and an individual action's detail view currently shows every file in whatever commit it landed in - which can be huge for bulk operations - rather than just the files relevant to that action.

## User Stories
- As a user reviewing an action's detail, I want to see only the files in its own provenance chain (its record, signature, and signer's public key - plus, for identity bootstraps specifically, the identity record that action establishes) so the detail pane stays readable no matter how large the underlying commit was.
- As a user who wants the full picture, I want a dedicated "Commits" view listing actual commits, so I can browse repository history directly instead of only individual actions.
- As a user in the Commits view, I want to select a commit and see everything it touched, so trimming the per-action view doesn't actually lose that information - it just moves.

## Core Requirements
- A new "Commits" filter/view in the Activity page's left pane, alongside the existing 5 (All/Visible Content/Identity/Bootstraps/Approvals), listing one row per commit.
- Commit rows are backed by a new, persisted `commits` table populated during read-model rebuild from git's own commit history (authoritative sha/date/author/message) - not inferred from activity rows, and not fetched from git at request time.
- Selecting a commit in the Commits view shows its full file listing - today's "show everything" behavior moves here unchanged.
- Selecting an individual action (in the other 5 views) shows only files in its own provenance chain: its own record(s) and its detached signature (if any) - not the rest of the commit. The signer's public key is shown as part of the signature's own entry (it's already named and linked there), not as a separate row - a signature and "its" key never disagree, so listing the same file twice adds nothing. Most actions have exactly one record; identity bootstraps are a compound action that also establishes a separate identity record, so their relevant set is record + identity record + signature(-with-key).
- The Commits view supports the same pagination, sorting, and filter-switching behavior already built for the other 5 views.

## Shared Component Inventory
- `paned_activity_filter_list.php` - extended with a 6th view entry ("Commits"), not forked.
- `fetchActivity()` / keyset cursor pagination / `resolveActivitySort()` - the Commits view gets a parallel query path following the identical fetch/cursor/sort shape already established for activity rows, since commits aren't activity rows themselves.
- `paned_activity_item_row.php` / `paned_activity_item_list.php` - reused for rendering commit rows, not forked, wherever the existing fields reasonably map.
- `activity_commit_manifest.php` - reused as-is for a selected commit's full file list; only its caller changes, from "every action sharing this commit" to "the selected commit itself."
- `SqliteActivityCommitManifestCache` - reused as-is; already keyed by commit sha regardless of whether that sha comes from an action or a browsed commit.
- `sourceCommitFileRole()` - reused to build a new, narrower "this action's own files" selection from the same role classification already computed for the full manifest.
- `ReadModelBuilder` - extended with a new indexing stage alongside `indexActivity`/`indexPosts`/etc., so the `commits` table is produced by the same rebuild pipeline and transaction as everything else, not a separate build path.

## Simple User Flow
1. User opens Forte Activity; the left pane now shows 6 filters, including "Commits".
2. User selects an action in one of the original 5 views; the detail pane shows that action plus only its own relevant files.
3. User switches to "Commits"; the list pane shows one row per commit, paginated and sortable like the other views.
4. User selects a commit; the detail pane shows that commit's full file listing, exactly as today's commit manifests do.
5. Commit rows continue to use today's existing link mechanism; anything richer (e.g. linking directly to a commit view) is explicitly deferred.

## Success Criteria
- An action's detail view only ever shows files in that action's own provenance chain (record(s), signature - with the signer's public key named on the signature itself, not a separate row) - regardless of how many other, unrelated files the underlying commit touched.
- A commit's full file list is still reachable in full, just from the Commits view instead of from every action that happens to share it.
- The Commits view supports the same "Load more" pagination and sort options as the other 5 views.
- Serving the Commits view's list requires no git subprocess calls at request time - commit metadata comes from the persisted table.
