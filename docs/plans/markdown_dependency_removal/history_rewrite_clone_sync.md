# Coordinated history rewrite and clone synchronization

Status: preparation only. User and assistant coordinate GitHub. Wait for user OK
before rewriting; review concrete replacement refs before shared publication.
No commands below have been executed to change any clone.

## Before the freeze

User finishes work, integrates each clone with current main and pushes all work
that must survive, including retained feature branches. Updating main alone does
not preserve commits on other branches, detached worktrees, stashes or untracked
files. Review those separately. Keep private configuration and runtime data local;
do not commit secrets to preserve them. The assistant will preserve this removal
branch and its deferred untracked planning drafts during the same reconciliation.

Observed repositories (2026-10-21; repeat inventory at freeze):

| Repository | Working files | Stashes | Linked worktrees beyond primary |
| --- | --- | ---: | --- |
| `~/v3` | Clean | 4 | 1 detached |
| `~/agent/v3-claude` | Modified study document; two untracked plans | 2 | None |
| `~/agent/v3-claude-2` | Clean | 0 | None |
| `~/agent/v3-codex` | Two untracked deferred extension plans | 1 | 3 detached; 1 missing/prunable registration |
| `~/agent/v3-codex-2` | Clean | 0 | 1 missing/prunable registration |
| `~/agent/v3-codex-3` | Modified study document; two untracked plans | 0 | None |
| `~/agent/v3-codex-4` | Untracked plans and mockups | 0 | 1 detached |

Correction: these are under `~/agent/`, singular; `~/agents/v3-*` matched nothing.
Each primary repository has independent Git storage; its linked worktrees share
that storage. Seven primary repositories were discovered, including the active
checkout. Full paths, heads and branch counts are in
[the inventory](./history_cleanup_inventory.json). A clean status says nothing
about unpushed branches. Linked worktree contents have not yet been audited.

When ready, user confirms consolidation/push complete and freezes agent jobs,
automatic fetch/push/sync processes and collaborator writes. Assistant takes a
fresh remote/local ref snapshot and resolves every local-only branch, stash and
detached commit with the user. Confirm the scope of other deployment clones,
forks, backups and hosted retention before declaring historical removal complete.

## Preservation checklist before the rewrite

Based on the recorded inventory, not a fresh audit. Check an item only after
confirming where its work is preserved. Keep private files and unapproved planning
drafts outside Git when appropriate; a stash alone is not a durable handoff.
No deletion, reset or rewrite is authorized by this checklist.

### `~/v3`

- [ ] Review all 149 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Recheck tracked and untracked working files; primary checkout was clean at inventory time.
- [ ] Review all 4 stashes, including any untracked content; apply and preserve needed changes or record an explicit discard decision.
- [ ] Inspect linked worktree `/home/wsl/.codex/worktrees/1ee8/v3` for modified/untracked files and detached commits; preserve needed work in reviewed files or a pushed branch.
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-claude`

- [ ] Review all 103 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Preserve working files: `M docs/plans/effort_estimation_trial.md`.
- [ ] Preserve working files: `?? docs/plans/instance_content_push_debug_log.md`.
- [ ] Preserve working files: `?? docs/plans/instance_content_push_step1_solution_assessment.md`.
- [ ] Review all 2 stashes, including any untracked content; apply and preserve needed changes or record an explicit discard decision.
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-claude-2`

- [ ] Review all 20 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Recheck tracked and untracked working files; primary checkout was clean at inventory time.
- [ ] Confirm no new stashes have been created (none recorded).
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-codex`

- [ ] Review all 43 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Preserve working files: `?? docs/plans/markdown_renderer_extension_debug_log.md`.
- [ ] Preserve working files: `?? docs/plans/markdown_renderer_extension_step1_solution_assessment.md`.
- [ ] Review all 1 stashes, including any untracked content; apply and preserve needed changes or record an explicit discard decision.
- [ ] Inspect linked worktree `/home/wsl/.codex/worktrees/39c4/v3-codex` for modified/untracked files and detached commits; preserve needed work in reviewed files or a pushed branch.
- [ ] Inspect linked worktree `/home/wsl/.codex/worktrees/7bff/v3-codex` for modified/untracked files and detached commits; preserve needed work in reviewed files or a pushed branch.
- [ ] Inspect linked worktree `/home/wsl/.codex/worktrees/d69d/v3-codex` for modified/untracked files and detached commits; preserve needed work in reviewed files or a pushed branch.
- [ ] Confirm missing worktree `/tmp/forum-image-baseline` has no recoverable work; record disposition of its stale registration.
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-codex-2`

- [ ] Review all 24 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Recheck tracked and untracked working files; primary checkout was clean at inventory time.
- [ ] Confirm no new stashes have been created (none recorded).
- [ ] Confirm missing worktree `/tmp/case-tags-baseline` has no recoverable work; record disposition of its stale registration.
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-codex-3`

- [ ] Review all 13 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Preserve working files: `M docs/plans/effort_estimation_trial.md`.
- [ ] Preserve working files: `?? docs/plans/feature_flags_security_review_cleanup_debug_log.md`.
- [ ] Preserve working files: `?? docs/plans/feature_flags_security_review_cleanup_step1_solution_assessment.md`.
- [ ] Confirm no new stashes have been created (none recorded).
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### `~/agent/v3-codex-4`

- [ ] Review all 1 local branches; integrate or push every commit to retain and verify it exists on GitHub.
- [ ] Preserve working files: `?? docs/plans/lcd_color_choices_debug_log.md`.
- [ ] Preserve working files: `?? docs/plans/lcd_color_choices_mockups/`.
- [ ] Preserve working files: `?? docs/plans/lcd_color_choices_step1_solution_assessment.md`.
- [ ] Confirm no new stashes have been created (none recorded).
- [ ] Inspect linked worktree `/home/wsl/.codex/worktrees/36b7/v3-codex-4` for modified/untracked files and detached commits; preserve needed work in reviewed files or a pushed branch.
- [ ] Bring retained work up to current main, push it, and verify the resulting remote commits.

### Final handoff

- [ ] Verify required private configuration and runtime data are preserved locally without committing secrets.
- [ ] Account for every retained branch, stash, untracked file and detached worktree across all seven repositories.
- [ ] Confirm all intended pushes are complete and pause agents, scheduled Git jobs and collaborator writes.
- [ ] Tell the assistant consolidation is complete; request a fresh inventory to confirm nothing was missed.
- [ ] Review the synchronization procedure and give OK before the rewrite begins.

## Rewrite and publication

Use an isolated repository to rehearse removal without modifying these working
copies. Preserve all newly pushed work and integrate the dependency-removal branch
with current main. Review path/blob inventory, source/commit mappings, resulting
trees, tags and replacement refs; retain the frozen measurement corpus under its
mapped revision. Do not install a new rewrite dependency without consent.

After user OK for the rewrite, perform and verify the rehearsal. Present exact
old/new remote refs for publication review. Publish only the agreed GitHub refs
using expected-old-value protections; never push to the unrelated `fdp` upstream.
Record any server-retained refs/caches requiring host assistance. No blanket
mirror push from a mixed-remotes working repository.

## After publication: synchronize each clone before resuming work

Delaying clone updates until this point is fine while writes remain paused.
Do not pull, merge or push from an old clone: that can restore the removed history.
Update linked worktrees and background jobs as part of their parent repository.

Preferred approach for this many historical branches: create a fresh sibling
clone using GitHub's HTTPS URL (not a local clone or shared object store):

```bash
git clone https://github.com/gulkily/v3.git /absolute/path/to/new-clean-checkout
```

Choose each destination after confirming it is absent. Compare tracked files and
reapply only reviewed uncommitted file changes and required private configuration
or runtime state. Do not copy `.git`, old vendor files or whole directory trees.
Restore retained feature branches from the rewritten origin. Unpublished changes
must be reviewed and reapplied on new history, with tests; do not merge an old
branch or apply an entire historical commit range blindly.

Validate the new checkout, application configuration and required work before
switching services/jobs to its path. Then retire the old clone and its linked
worktrees with the user after all preserved work has been verified. Keeping an
old clone as a temporary recovery copy means local historical removal is still
pending; inventory its location and dispose of it at agreed closeout.

In-place synchronization is also possible after consolidation, but needs a
per-clone command sheet based on its final inventory. That sheet will:

1. Preserve reviewed working files and account for every local branch, stash,
   detached worktree and tag before any destructive command.
2. Fetch the agreed rewritten origin refs with explicit forced refspecs where
   required. Reconcile tags separately; ordinary fetch does not replace old tags.
3. Move main and retained feature branches to verified mapped commits, and update
   every linked worktree. Remove obsolete refs only after their work is accounted
   for. A hard reset is suitable only for a confirmed clean, preserved worktree.
4. Check remote-tracking refs, other remotes, replacement/backup refs, stashes and
   worktree HEADs for old ancestry. Preserve unrelated upstream/subtree history.
5. Only after verification and explicit cleanup authorization, expire obsolete
   reflogs and prune unreachable objects with all concurrent Git writers stopped.
   Fetch/reset alone does not remove old source from object storage.
6. Verify target package paths/blobs are absent from agreed reachable history and
   remaining object storage, confirm expected mapped heads, and run normal checks.

The exact in-place reset/ref-deletion/pruning commands are intentionally pending
that inventory and preservation review. Fresh clones reduce the chance of leaving
old refs behind. GitHub retained data and other copies still need their own
verification; neither approach alone proves universal erasure.

## Current gate

User finishes consolidation and push, then gives OK. Assistant does not rewrite
existing history or publish replacement refs while waiting. Stage 4 rehearsal and
Stage 5 publication/cleanup remain incomplete.
