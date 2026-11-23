> **Feature plan:** [Step 1](./deploy_rollback_step1_solution_assessment.md) · [Step 2](./deploy_rollback_step2_feature_description.md) · [Step 3](./deploy_rollback_step3_development_plan.md) · [Step 4](./deploy_rollback_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_rollback`; host ledger [deploy_rollback.md](../effort_ledger/deploy_rollback.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.60

## Original Query

As an operator, I want to keep a record of which versions have been deployed and to be able to easily roll back to a previously deployed version.

## Understood Intent

The operator deploys the application checkout from Tools → Deploy, often on an iPhone, or over SSH with `./v3 deploy`. After a bad deploy they want to see the versions the site has run and return to one of them in a click. Today the only way back is `git reset` over SSH, which also has to keep the server's local edit to `public/.htaccess`. "Version" means a commit of the application checkout, not the content repository.

## Problem

`state/logs/app_update.log` records every attempt, but it is rotated at 256 KB, mixes refusals and queue entries with real deploys, and only its last entry is shown; nothing can move the checkout back to an earlier commit.

Concepts touched: [application update](../../concepts/application_update.md) (`AppUpdate.php`, `DeployController.php`, `scripts/deploy.php`, `templates/pages/deploy.php`), extended in every option; [read-model rebuild](../../concepts/read_model_rebuild.md), used unchanged. Feedback: no matching item found in `docs/feedback/items/`.

## Shared by every option

- Only a signed-in root-approved operator can roll back, under the same same-origin and server-side gate as Deploy now.
- A rollback goes only to a commit that this site has deployed before. It is never an arbitrary commit or branch.
- A rollback uses the deploy lock and the same set-aside of local edits (`git stash`, then put back), so `public/.htaccess` survives it.
- After a rollback, the existing read-model check runs with the older code. It queues a rebuild if the schema differs, as a deploy does today.
- The checkout stays on `main`, behind `origin/main`. The next ordinary deploy moves it forward again, so nothing new is needed to undo a rollback.
- `./v3 deploy` gets matching `--history` and `--rollback=<commit>` options for SSH use.

### Schema and record format changes

A rollback can cross three kinds of format change, each with a different risk:

- **Read model** (`post_index.sqlite3`): derived and disposable. The read-model check needs an exact match on `SCHEMA_VERSION`, so a rollback across a schema change queues a full rebuild. Rolling forward later queues another. The cost is time on the "Back in a moment" page, not data.
- **Side SQLite stores** (sessions, visitor statistics, Fastmod scores, agent replies): newer code adds columns with `ALTER TABLE ADD COLUMN` and never removes them. Older code most likely ignores the extra columns, but Step 2 must confirm that for each store.
- **Canonical records** in the content repository: the real risk. Newer code may have written records that older code can't read. For example, code from before PR #189 doesn't read `Target-ID` retractions, so a rebuild with that code could miss them. Git still holds every record, so nothing is lost, and rolling forward restores the correct view.

Before a rollback runs, the confirmation lists the commits it undoes. It warns when that range changes `SCHEMA_VERSION`, `docs/specs/` or the record parsers under `src/ForumRewrite/Canonical/`. The warning never blocks: the operator decides.

## Option A — A deploy history file plus a rollback action

**Estimate: 0.60**
Main driver: one new rollback path in `AppUpdate.php` with the stash cases tested, and a history table on the Deploy page. Uncertainty: low.

Each deploy that changes the commit, and each rollback, appends one `Key: value` block to a new `state/logs/deploy_history.txt`. A block holds the time, the operator, the mode, the old and new commit, and the commit subject. The file is never rotated, because it grows by one short block per deploy. The Deploy page lists recent entries, marks the running one, and puts a Roll back button on each earlier version.

- Pro: one plain file an operator can read over SSH, in the format the project already uses.
- Pro: refusals and queue entries stay in the attempt log, so the history reads as a list of versions.
- Con: the history lives in `state/`, so losing that directory loses the record, though Git still has the commits.

## Option B — Git refs as the record

**Estimate: 0.65**
Main driver: Option A's rollback path, plus writing and listing annotated tags under a private ref name. Uncertainty: medium.

Each deploy creates an annotated tag such as `refs/deploys/20261120-171500` in the application checkout. The tag message holds the same `Key: value` fields. The history page lists those refs, and a rollback resets to one.

- Pro: the record sits beside the commits, and `git gc` never removes a deployed commit.
- Con: the refs are hidden from ordinary `git log` and `git tag`, so the record is harder to find than a file.
- Con: it adds writes to the checkout that a deploy promises to keep to a fast-forward.

## Option C — Release directories with a `current` link

**Estimate: 1.40**
Main driver: a new layout on the host, with one directory per release and a symlink switched on deploy and rollback. Uncertainty: high.

Each deploy checks out into its own directory, and the web root follows a `current` symlink. A rollback switches the link back.

- Pro: switching is instant and atomic, with no Git operation at rollback time.
- Con: it replaces the `~/v3` checkout layout, the `.htaccess` handling and the DreamHost setup the operator uses today.
- Con: it is far more than the request needs.

## Recommendation

Option A. It answers both halves of the request with one plain file and one action that reuses the deploy's lock and stash handling. The fixed list of versions keeps rollback from becoming a general "check out anything" tool. Option B's protection against `git gc` matters little, because deployed commits stay on `main`.

Vertical slice: on prod, deploy a new commit from Tools → Deploy on the iPhone. The history shows it on top as running. Tap Roll back on the previous entry. The site serves the older commit, `public/.htaccess` keeps its local edit, and the history gains a rollback entry. Deploy now then brings the site back to `origin/main`.

## Open questions for the maintainer

- Should the header Deploy now prompt stay quiet after a rollback until the operator deploys again? This plan assumes it keeps offering the update as it does today.
- How many entries should the page show? This plan assumes the last 20, with the full file available over SSH.

## Enhancement Requests

- 2026-11-20, maintainer: "what about schema changes?" In scope. Added the section on schema and record format changes, and a non-blocking warning at rollback time. The estimate stays at 0.60, because the warning reuses the existing incoming-commit listing.

— Claude (Opus 5.5), 2026-11-20 (revised the same day)

## Study snapshot and approval state

| Field | Value |
| --- | --- |
| Checkpoint | Step 1 handoff |
| Projected total (recommended scope, Option A) | 0.60 |
| Alternatives (not summed) | A 0.60, B 0.65, C 1.40 |
| Reference / protocol | R1 / P2 |
| Rater | Claude, single exposed rater |
| Exposure | Estimated after reading `AppUpdate.php`, `DeployController.php` and the Deploy page template, so not strictly prospective |
| Baseline | `eb0f5177` |
| Approval | **Approved Step 1** by the maintainer, 2026-11-20, with both open assumptions accepted |
