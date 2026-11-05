# Deploy pending commits — one-shot

Cycle: `v3/deploy_pending_commits`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (log created at closeout; project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/deploy_pending_commits`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.20**

Status: planning; next permitted action: implementation on `feat/deploy-pending-commits` after the planning commit.

## Request and authorization

Feedback item: [PF-20261104-F49](../../feedback/items/PF-20261104-F49.txt), "Deploy shows commits and queue delay".

Original request, thread [thread-20261103060933-51ef9554](https://zenmemes.com/threads/thread-20261103060933-51ef9554) (ilyag, 2026-11-03): "this page should give me all the info it can about what the update will do, which commits will be added. also, the "Deploy" button should communicate that it's done through the queue with delay"

Authorization, 2026-11-05: the maintainer pasted the thread and asked "let's work on this one, please. is it a one-shot or full?". The agent recommended a one-shot covering an incoming-commit list with a diffstat, clearer queued-deploy wording, and the sibling item PF-20261104-F41 as a follow-up. The maintainer replied "go ahead please". Under [thread to pull request](../../agents/thread_to_pr.md) this authorizes one-shot execution of that bounded outcome, pushing the branch, opening the PR and replying in the thread.

Exclusions: merge, deployment, any change to how a deploy runs (fetch, fast-forward, lock, queue order), and PF-20261104-F41 (making the page understandable for non-technical operators).

## Outcome and work type

Application change. An operator opening Tools → Deploy after Check for updates sees, before pressing anything:

- the commits the update will add (short hash, subject, author, date), up to 20, with "and N more" beyond that;
- a one-line summary of the change size (files changed, lines added and removed);
- a warning when the update changes a file the server keeps a local edit to, because that is the case where a deploy can stop with `local_changes_conflict`;
- buttons that say what they do: the queued button reads "Queue deploy" and a line under the buttons says it waits for the task worker, normally starting within about a minute, while Deploy now runs immediately. While queued, the status line says how long it has been waiting.

`./v3 deploy --status` and `--check` print the same incoming commits and size as `Key: value` lines.

## Approach and reuse decisions

- **State:** extend `AppUpdate::inspect()` (local-only, no network) with `incoming_commits`, `incoming_more`, `incoming_stat` and `incoming_local_overlap`, computed only when the checkout is behind `origin/main`. Same `gitValue()`/`git()` helpers, same `HEAD`…`origin/main` range the behind count already uses. No new class.
- **Page:** extend `templates/pages/deploy.php`'s existing `codebase-facts` table with an "Incoming" row; no new component or stylesheet beyond a small list rule in `tool-details.css` next to `.deploy-actions`.
- **Commit links:** reuse `SourceRepository::fromProjectRoot()` (the `FORUM_SOURCE_REPOSITORY_URL` setting) for commit and compare links, linking only when the host is `github.com`, whose `/commit/` and `/compare/a...b` paths are known; otherwise plain text.
- **Queue wording:** reuse the existing worker-quiet sentence and status line; the queued task's `requested_at` (already returned by `latestByType()`) gives the waiting time, passed to the script as a data attribute.
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
Planning commit: pending.

## Study history

- **Intake, 2026-11-05T14:45Z, 0.20.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: `AppUpdate`, `DeployController`, the template, page script, CLI and tests were inspected before rating. Scope v1 as above. R1 hashes not re-verified this cycle. Compared with R1's duplicate preview suppression (0.10) and about-copy integration (0.20), and recent cycles source repository link (0.12) and Chicago post actions (0.15).

## Dated execution history

- 2026-11-05: Record created before implementation.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
