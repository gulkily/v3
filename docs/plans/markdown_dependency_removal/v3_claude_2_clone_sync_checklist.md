# `~/agent/v3-claude-2` in-place clone sync checklist

Started 2026-10-23. Moves this clone onto the rewritten history described in
[clone sync](./history_rewrite_clone_sync.md), using the
[commit map](./history_rewrite_commit_map.json). Removed paths: `third_party/markdown/`.

A pre-sync snapshot of old branch/stash SHAs was kept in session scratch space; those objects were pruned below.

## Working files

- [x] Stash modified/untracked files as `stash@{0}` ("pre-rewrite-migration"):
  `effort_estimation_trial.md`, `unsigned_attribution_debug_log.md`,
  `unsigned_attribution_step1_solution_assessment.md`.
- [x] Pop the stash onto new `main`. `effort_estimation_trial.md` conflicted (both sides appended sections); kept upstream's sections, then `v3/unsigned_attribution`. Left uncommitted.

## main

- [x] Verify all 66 local-only commits are in the map and the mapped tip is an ancestor of `origin/main`.
- [x] Reset `main` to `origin/main` (`1a358bc9`).

## Mapped branches (pointer moves)

For each: target exists locally, tree equals old tip minus `third_party/markdown/`, then `git branch -f`.
Done 2026-10-23: all 18 trees were byte-identical to their old tips.

- [x] `chore/railspike-0.2.0-beta.1` → `4ca31978`
- [x] `chore/repository-directory-cleanup` → `eeff9a9a`
- [x] `feat/agent-api-docs` → `26ce503c`
- [x] `feature/backup_page_redesign` → `a6c749c7`
- [x] `feature/feature_flag_change_pages` → `55bc90d0`
- [x] `feature/forte-dark-theme` → `9d1e5fd9`
- [x] `feature/music-media-embeds` → `7ed8b14e`
- [x] `feature/qdb-site-news-compact` → `1408de3e`
- [x] `feature/server-cooperative-content-deletion` → `078b74a6`
- [x] `feature/signed-thread-label-records` → `96831d0b`
- [x] `feature/status-frozen-frontend-diagnosis` → `df763744`
- [x] `feature/top-five-media-embeds` → `7ac91de3`
- [x] `feature/username-change-gate` → `59497d30`
- [x] `feature/youtube-embed-layout` → `e12c974a`
- [x] `fix/backup-page-tools-nav-order` → `c7535a96`
- [x] `fix/lock-timeout-private-config` → `c2330cd8`
- [x] `gpg_agent_leak` → `fa3f077e`
- [x] `offline-indicator-corner-links` → `b463ae5f`

## Unpushed branch: `feat/web-ui-instance-upgrade`

Five unpushed commits (2026-10-19, Railspike stages 1–4), not in the map.

- [x] Confirm the commits do not touch `third_party/markdown/`.
- [x] No rebase needed: no ancestor was rewritten (base `cf0aa77a` maps to itself and is on `origin/main`). Branch left unchanged.

## Verification

- [x] No local branch or `origin/*` ref reaches an old (pre-rewrite) commit.
- [x] No branch history contains `third_party/markdown/`.
- [x] Tag `fdp-before-2026-10-02` is unchanged by the rewrite; no `refs/original` or `refs/replace`.

## Cleanup (user-authorized 2026-10-23)

- [x] Drop the stash after its contents were applied.
- [x] Confirm no other process was using this repository, then expire all reflogs and run `git gc --prune=now`.
- [x] All 424 target blobs from the [source inventory](./history_rewrite_source_inventory.json) were present before pruning; 0 remain. Old main `9f33caff` and old upstream main `afe40311` are gone; `git fsck` clean. Pack size 43.28 MiB → 15.08 MiB.

This clone no longer holds the removed history. The private recovery bundles and the other six clones are outside this checklist.
