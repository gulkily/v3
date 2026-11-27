# Deploy returns a merged branch to main

Cycle v3/deploy_merged_branch · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_merged_branch`; host ledger [deploy_merged_branch.md](../effort_ledger/deploy_merged_branch.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.12**

Status: implemented, PR open; next permitted action: maintainer returns the server to `main` over SSH once, merges, and deploys.

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

Starting revision and branch: `83d6798f`, `fix/deploy-merged-branch`. Planning commit: `928c1db`.

## Study history

- 2026-11-27 UTC, intake and entry: **0.12**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-27 UTC, closeout: retrospective **0.12**. Same rater.

## Outcome

- `AppUpdate::runDeploy` no longer refuses a non-`main` checkout up front. After fetching, `returnToMain()` checks that `HEAD` and any local `main` are contained in `origin/main`, then runs `git checkout -B main` (same commit, so no file or local edit changes) and the normal fast-forward follows. The message ends "Switched to main from <branch>." Otherwise `wrong_branch` is refused with "…which has commits not on origin/main; resolve over SSH." Rollback is unchanged.
- `./v3 deploy --help` and the application update concept describe the return.
- Header: `.deploy-prompt` wraps and is capped at the eyebrow's width; on phones (below 640px) the eyebrow may shrink while the prompt shows; the status breaks long words. On wider screens the prompt is capped at 13rem so the nav keeps most of its room.

## Verification

- Passed: `./v3 test --smoke AppUpdateTest` (540 run, 540 passed). New tests: unmerged branch refused and untouched; server `main` with its own commit refused; merged hotfix branch deploys, ends on `main` and keeps a local tracked edit; detached HEAD already at `origin/main` switches and reports up to date. The old off-main test became the unmerged-branch test.
- Passed, headless Chrome with the real stylesheets and the prompt plus the production refusal text injected: at 375px the page width stays 375px in Gloss, Word97, Bubblegum, Arena and Underground (before: 604, 375, 672, 603, 781). At 700px and 1280px nothing overflows and the prompt no longer overlaps the nav. At 700px the nav already wraps to two columns without the status; with it, the nav is a little narrower.
- Not verified: on the maintainer's phone; Zalgo (flag off locally); the switch on production. Not run: the full suite.
- Seen, out of scope: in Underground the header Deploy now button's text is invisible (same colour as its fill), before and after this change.

## Final review

Diff: `AppUpdate.php`, `deploy.php` help, `site.css` deploy prompt rules, `AppUpdateTest.php`, the concept, this record, the index, the ledger and the debug log. No script, controller or template change.

Recovery: production is still on `fix/merge-about-slot` and refuses deploys, including this one, until switched once over SSH (`git stash && git checkout main && git pull && git stash pop` in `~/v3`).

Commit, merge and publication: pushed to `fix/deploy-merged-branch` with an open PR; not merged.

— Claude · 2026-11-27 UTC · Model: claude-opus-5-5
