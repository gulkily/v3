# Deploy pending commits — one-shot

Cycle: `v3/deploy_pending_commits`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](deploy_pending_commits_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/deploy_pending_commits`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.20**

Status: technically complete on `feat/deploy-pending-commits`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261104-F49](../../feedback/items/PF-20261104-F49.txt), "Deploy shows commits and queue delay".

Original request, thread [thread-20261103060933-51ef9554](https://zenmemes.com/threads/thread-20261103060933-51ef9554) (ilyag, 2026-11-03): "this page should give me all the info it can about what the update will do, which commits will be added. also, the "Deploy" button should communicate that it's done through the queue with delay"

Authorization, 2026-11-05: the maintainer pasted the thread and asked "let's work on this one, please. is it a one-shot or full?". The agent recommended a one-shot covering an incoming-commit list with a diffstat, clearer queued-deploy wording, and the sibling item PF-20261104-F41 as a follow-up. The maintainer replied "go ahead please". Under [thread to pull request](../../agents/thread_to_pr.md) this authorizes one-shot execution of that bounded outcome, pushing the branch, opening the PR and replying in the thread.

Exclusions: merge, deployment, any change to how a deploy runs (fetch, fast-forward, lock, queue order), and PF-20261104-F41 (making the page understandable for non-technical operators).

## Outcome and work type

Application change. An operator opening Tools → Deploy after Check for updates sees, before pressing anything:

- a one-line summary of the update (commit count, files changed, lines added and removed);
- under it, the commits the update will add (short hash, subject, author, date), newest first, up to 20, with "and N more" beyond that; merge commits are left out, and the list is collapsed when it is longer than five;
- a warning when the update changes a file the server keeps a local edit to, because that is the case where a deploy can stop with `local_changes_conflict`;
- a queued button labelled "Queue deploy"; after pressing it the status line says it usually starts within a minute, then how long it has waited for the task worker.

`./v3 deploy --status` and `--check` print the same incoming commits and size as `Key: value` lines.

## Approach and reuse decisions

- **State:** extend `AppUpdate::inspect()` (local-only, no network) with `incoming_commits`, `incoming_more`, `incoming_stat` and `incoming_local_overlap`, computed only when the checkout is behind `origin/main`. Same `gitValue()`/`git()` helpers, same `HEAD`…`origin/main` range the behind count already uses. No new class.
- **Page:** extend `templates/pages/deploy.php`'s existing `codebase-facts` table with an "Incoming" row; no new component or stylesheet beyond a small list rule in `tool-details.css` next to `.deploy-actions`.
- **Commit links:** reuse `SourceRepository::fromProjectRoot()` (the `FORUM_SOURCE_REPOSITORY_URL` setting) for commit and compare links, linking only when the host is `github.com`, whose `/commit/` and `/compare/a...b` paths are known; otherwise plain text.
- **Queue wording:** rename the button and reuse the existing status line and worker-quiet sentence; no new static help text (`AGENTS.md` UI copy: prefer removing text, explain only what the interface cannot show). The queued task's `requested_at` (already returned by `latestByType()`) gives the waiting time after a reload, passed to the script as a data attribute.
- **CLI:** extend `scripts/deploy.php`'s `$printState`.
- **No new dependency, route, table, schema or format change.**

## Concepts touched

[Application update](../../concepts/application_update.md): changed (scenario gains the incoming-commit preview and queued wording). No inventory additions.

## Risks

- **Page cost:** `inspect()` runs on every page load and status poll. Mitigation: the extra Git calls (one `log`, two `diff`) run only when behind, are local, and are bounded by the 20-commit limit.
- **Stale preview:** the list reflects the last fetch, not GitHub now. Mitigation: it sits in the row that already shows "(checked …)", and Check for updates refreshes it.
- **Diverged checkout:** when the server is also ahead, a deploy refuses anyway; the list shows `origin/main`'s side only (`HEAD..origin/main`), and the stat uses the merge base (`HEAD...origin/main`).

## Verification and recovery plan

- Tests: `AppUpdateTest` for incoming commits, the more-count beyond the limit, the stat and local-overlap; `DeployPageTest` for the rendered row, links and button wording.
- Run the smoke set plus those tests; the full suite is not run.
- Render `/tools/deploy/` through the local server as an operator with a checkout that is behind, at phone and desktop widths, in the default theme and Word97/Chicago (table and buttons are theme-styled).
- Recovery: reverting the commit restores the previous page; no state is written.

Starting revision and branch: `058b2c6b069e023802871aa141f570a6407217a5` on `main`; branch `feat/deploy-pending-commits`.
Planning commit: `708d4b8e` (confirmed with Git).

## Study history

- **Intake, 2026-11-05T14:45Z, 0.20.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: `AppUpdate`, `DeployController`, the template, page script, CLI and tests were inspected before rating. Scope v1 as above. R1 hashes not re-verified this cycle. Compared with R1's duplicate preview suppression (0.10) and about-copy integration (0.20), and recent cycles source repository link (0.12) and Chicago post actions (0.15).
- **Execution entry, 2026-11-05T14:47Z, 0.20 reaffirmed.** Same scope v1; no new evidence between intake and entry.
- **Closeout, 2026-11-05.** Projection 0.20 reaffirmed. Retrospective scope assessment **0.20**, from the same single exposed rater, not an independent rating. Corrections: none. Additions: none from the requester; the merge filter and collapsed list are design decisions inside scope v1. Delivered scope v1 in full. Maintainer comfortable-fit judgment and acceptance pending. No active-work measurement was collected.

## Dated execution history

- 2026-11-05: Record created before implementation; planning commit `708d4b8e`. An earlier local version carried a `Feedback:` trailer without the item update; it was amended before anything else happened and never pushed.
- 2026-11-05, implementation: `AppUpdate::incoming()` behind `inspect()`, `INCOMING_LIMIT = 20`; Incoming row, `Queue deploy` label and `data-deploy-queued-at` in `deploy.php`; waiting time in `deploy_control.js`; `commitLinkBase()` in `DeployController`, wired from `SourceRepository` in `Application`; `Incoming-*` lines in `scripts/deploy.php`; runbook and concept entry updated.
- 2026-11-05, decision after rendering: with 20 commits the phone layout pushed the buttons several screens down, and most entries in this repository's history are merges that repeat their branch's commits. The list now leaves out merges (`--no-merges`, with "more" counted the same way) and sits in a `<details>` whose summary carries the count and size, open when it has five commits or fewer. The local-edit warning stays outside it. The Behind row still counts all commits, merges included, so the two numbers can differ.
- 2026-11-05, decision: no static help line about the queue under the buttons. The label says it is queued; the status line says when it usually starts and then how long it has waited; the existing sentence for a quiet worker stays.
- 2026-11-05, scope note: the Incoming row shows to everyone who sees the page's Git facts (read-only viewers already see commit and branch), matching the existing table. No new exposure beyond commit subjects and author names that are on the public repository.

## Outcome

Delivered: after Check for updates, an operator sees what a deploy would bring in (count, size, the commits with links to GitHub, and a warning when it touches a file the server keeps a local edit to) before choosing Queue deploy or Deploy now; a queued deploy shows how long it has waited. The CLI prints the same. Limitations: the preview reflects the last fetch, not GitHub now; production shows it only after deployment, and the first deploy that contains it still shows the old page until it lands. Follow-up: [PF-20261104-F41](../../feedback/items/PF-20261104-F41.txt), making the page understandable for non-technical operators.

## Verification

- Passed: `./v3 test --smoke DeployPageTest AppUpdateTest`, 457 run, 457 passed. New tests: incoming commits and size after a check, nothing before a fetch, the 20-commit limit and more-count, merges left out, local-edit overlap, the rendered row with GitHub links and HTML escaping, plain hashes for a non-GitHub repository, and the `Queue deploy` label on the operator page.
- Passed: rendered the operator page from a checkout behind this repository's `main` and served it with the real assets; Playwright with Chrome at 390 and 1280 px in light, dark, Word97, Chicago and Midnight: no horizontal overflow, Incoming row and Queue deploy visible. Screenshots checked for a long update (collapsed) and a two-commit update with a local-edit overlap (open).
- Passed: with stubbed POST responses, pressing Queue deploy shows "Queued for the task worker. It usually starts within a minute." and the first poll shows "Queued; waiting for the task worker… 0:05".
- Passed: the CLI state printer against the same checkout prints `Incoming-Size`, `Incoming-Commit` and `Incoming-Changes-Local-Edit`.
- Passed: `./v3 feedback check` (0 problems; 21 pre-existing review notes) and `./v3 feedback index`; `git diff --check`.
- Not run: the full suite (maintainer runs it). Not verified: a real queued deploy picked up by cron on the server, an iPhone, and the page-load waiting time from a real queued task (covered only by reading `requested_at`'s `gmdate('c')` format, which `Date.parse` accepts). Inapplicable: nav icons (no destination added or renamed), read-model and dependency checks (none changed; `playwright-core` was installed from the existing `package.json` for screenshots only).

## Final review

Diff checked for duplication (one new private method in `AppUpdate`, no new component; list styles sit with the existing `.deploy-actions` rules), file scope (deploy page, its script and CSS, CLI, tests, runbook, concept entry, feedback item, this record, index and ledger) and documentation (runbook and concept entry describe the new row and label).

## Commit, merge and publication status

Planning commit `708d4b8e`; completion commit on `feat/deploy-pending-commits` (reference pending in this record). Pushed to origin with a pull request; not merged, not deployed.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
