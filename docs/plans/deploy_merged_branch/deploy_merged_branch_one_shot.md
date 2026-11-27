# Deploy returns a merged branch to main

Cycle v3/deploy_merged_branch · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_merged_branch`; host ledger [deploy_merged_branch.md](../effort_ledger/deploy_merged_branch.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.12**

Status: planned; next permitted action: implement on `fix/deploy-merged-branch`.

## Original request

"I found a couple of different bugs with the deploy process. I'm screenshotting all of them. The main one here is that it's refusing to deploy because it's on a branch, but there's also a visual issue." (Ilya, 2026-11-27, three iPhone screenshots of zenmemes.com: Tools → Deploy showing "Last deploy: refused … The checkout is on fix/merge-about-slot, not main."; the board scrolled sideways with that message in the header; the board with the header Deploy now.)

## Authorization

A bug report with screenshots, read as authority for a bounded one-shot through an open PR, as for earlier deploy reports. Bounded to: (1) Deploy now / Queue deploy / `./v3 deploy` no longer refusing a checkout left on a branch whose commits are all on `origin/main`; (2) the header Deploy now message no longer widening the page on a phone. Not authorized: switching away from a branch that has unmerged commits, rollback changes, production access (the server needs one SSH `git checkout main` before it can deploy this fix; that is the maintainer's).

## Problem

1. After #439 broke every page, an agent hotfixed production by checking out `fix/merge-about-slot` on the server (PR #440: "The checkout remains on this fixed branch pending merge"). #440 was merged, but nothing returns the checkout to `main`, and `AppUpdate::runDeploy` refuses any branch other than `main` before fetching. Every deploy since is refused, while the header keeps offering Deploy now.
2. On a phone the header's `.eyebrow` is `flex: 0 0 auto` and `.deploy-prompt` is an unwrapping `inline-flex`, so a long status ("The checkout is on fix/merge-about-slot, not main. Open Deploy") makes the header wider than the screen and the whole page scrolls sideways.

## Outcome and work type

Application change.

- Deploy, off `main`: fetch as usual; when `HEAD` is contained in `origin/main` and the local `main` (if any) is too, point `main` at `HEAD` with `git checkout -B main` (no files change, local edits stay) and continue with the normal fast-forward. The result message says which branch it left. Otherwise refuse as before, saying the branch has commits not on `origin/main`.
- Already at `origin/main` but on the other branch: switch and report `up_to_date` with the switch noted.
- Header: the prompt may wrap; the status text wraps within the screen.

## Risks

- **Losing work on the server.** Only a branch fully contained in `origin/main` is left, and local `main` moves only when it is also contained there, so no commit becomes unreachable from `origin/main`. Local edits to tracked files survive because the switch keeps `HEAD`'s commit. Tests cover an unmerged branch (still refused) and a local `.htaccess`-style edit across the switch.
- **A failed fast-forward after the switch** leaves the checkout on `main` at the old commit, which is the state the next deploy expects. Acceptable.
- **CSS in other themes.** Check the header at 375px in Gloss, Word97, Bubblegum and Zalgo with a long status.

## Reuse decisions

- `AppUpdate::runDeploy` changed in place; the switch reuses `git()`/`gitValue()` and runs before the existing `moveKeepingLocalChanges` fast-forward. `runRollback` keeps its refusal (rollback never fetches, so it cannot know the branch is merged).
- `.deploy-prompt` / `.deploy-prompt__status` in `public/assets/site.css` changed in place. No script change.
- Concept [application update](../../concepts/application_update.md): one sentence added for the merged-branch return.

## Completion conditions

`./v3 test --smoke AppUpdateTest` passes with new tests: merged branch deploys and ends on `main`; branch with an unmerged commit is still refused and unchanged; merged branch already at `origin/main` switches and reports up to date; local tracked edit survives the switch. Headless Chrome at 375px shows no horizontal overflow with the long status. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: application update.

Starting revision and branch: `83d6798f`, `fix/deploy-merged-branch`. Planning commit: pending.

## Study history

- 2026-11-27 UTC, intake and entry: **0.12**. Claude, single exposed rater. R1 not reverified this session.

— Claude · 2026-11-27 UTC · Model: claude-opus-5-5
