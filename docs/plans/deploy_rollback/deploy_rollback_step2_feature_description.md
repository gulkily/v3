> **Feature plan:** [Step 1](./deploy_rollback_step1_solution_assessment.md) · [Step 2](./deploy_rollback_step2_feature_description.md) · [Step 3](./deploy_rollback_step3_development_plan.md) · [Step 4](./deploy_rollback_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_rollback`; host ledger [deploy_rollback.md](../effort_ledger/deploy_rollback.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.65**

## Problem

An operator can't see which versions the site has run. After a bad deploy, the only way back is `git reset` over SSH, which must also keep the server's local edit to `public/.htaccess`.

## Approved direction

Step 1 was approved on 2026-11-20 with Option A: a deploy history file plus a rollback action. The maintainer accepted both open assumptions: the header Deploy now prompt keeps offering the update after a rollback, and the page shows the last 20 versions. The section on schema and record format changes is part of the approved scope.

Concepts touched: [application update](../../concepts/application_update.md) (extended: a deploy history and a rollback); [read-model rebuild](../../concepts/read_model_rebuild.md) (used unchanged). Proposed correction: add "deploy history" and "rollback" to `application_update.md`. Feedback: no matching item in `docs/feedback/items/`.

## User stories

- As an operator, I want a list of the versions the site has run, so that I know what was live and when.
- As an operator on my iPhone, I want to roll back to an earlier version in one confirmed tap, so that I can undo a bad deploy without SSH.
- As an operator, I want to see what a rollback undoes and whether it crosses a format change, so that I can judge the risk first.
- As an operator over SSH, I want `./v3 deploy --history` and `--rollback=<commit>`, so that I have the same control from a terminal.

## Core requirements

- Every deploy that changes the commit, and every rollback, adds one `Key: value` entry to `state/logs/deploy_history.txt`. The file is never rotated. On first use, it is seeded from the `deployed` entries in `app_update.log` and `app_update.log.1`.
- A rollback targets only a commit listed in the history and still an ancestor of `HEAD` on `main`. Only a signed-in root-approved operator can start one, under the Deploy now gate.
- A rollback takes the deploy lock, sets aside local edits and puts them back. If that fails, it leaves the checkout as it was, as a deploy does. Afterwards the read-model check runs with the older code.
- The confirmation lists the commits being undone. It warns, without blocking, when they change `ReadModelMetadata::SCHEMA_VERSION`, `docs/specs/` or `src/ForumRewrite/Canonical/`.
- The next ordinary deploy moves the checkout forward again, with no extra step.

## Delivery scope

- Work type: application change to `AppUpdate.php`, `DeployController.php`, `scripts/deploy.php`, `templates/pages/deploy.php`, `deploy_control.js`, tests, the concept entry and the CLI reference.
- Out of scope: rolling back the content repository, rolling back to commits not in the history, pausing the header prompt, and any change to the side SQLite stores.

## Completion boundary

- Normal entry: Tools → Deploy on prod, or `./v3 deploy --rollback=<commit>` over SSH.
- End-to-end outcome: the site serves the chosen earlier commit, `public/.htaccess` keeps its local edit, and the history shows a rollback entry marked as running.
- Recovery: a refused or failed rollback leaves the commit and local edits unchanged and says why. Deploy now returns the site to `origin/main`.
- Release: merged to main, then tried on prod by the maintainer from the iPhone.

## Known user and environment

- The maintainer on an iPhone, at Tools → Deploy on zenmemes.com, host vps22365, checkout `~/v3`, with a local edit to `public/.htaccess`.
- The failure to handle: a rollback whose `.htaccess` edit doesn't apply on the older commit. The page then says nothing changed, and the operator can pick another version or deploy forward.
- Evidence: a prod rollback by one commit and back again, with `git status` over SSH showing the `.htaccess` edit still in place. A local test against a temporary clone does not replace this.

## Risks

- **Older code misreads newer records.** A rebuild with old code could miss records such as `Target-ID` retractions, so the site looks wrong until rolled forward. Validate early with a test that rolls back across a `SCHEMA_VERSION` change and checks the warning. Mitigate with the warning and the one-step roll forward.
- **A future column breaks old code.** `PrivateMessageReadState.php` and `PrivateMessageHistorySyncStore.php` insert rows without naming columns. Today no migration adds columns to those tables, so rollback is safe. If one ever does, older code would fail on insert. Mitigate with one sentence in `CONTRIBUTING.md` asking new columns to keep older inserts working.
- **The stash step fails on the older commit.** Validate first, with the existing `AppUpdateTest` temporary-clone setup and a conflicting local edit. Mitigate by reusing `restoreAt()` and the deploy's refusal path.
- **The celebration banner fires after a rollback.** Validate in the browser test. Mitigate by showing the banner only for a forward deploy.

## Shared component inventory

- `AppUpdate.php`: reuse the lock, `trackedChangedFiles()`, the stash handling, `restoreAt()`, `appendAttempt()` and the read-model check. Add the history file and a rollback beside `deploy()`, sharing one refusal policy.
- `DeployController.php` `submit()`: add a `rollback` action under the same same-origin and root-operator checks.
- `templates/pages/deploy.php` and `deploy_control.js`: reuse the `codebase-facts` table, the commit links and the incoming-commit list for the history and the confirmation. No new widget.
- `scripts/deploy.php`: add the two options beside `--check` and `--status`.
- `deploy_celebrate.js`: reused, limited to forward deploys.

## User flow

1. The operator opens Tools → Deploy and sees the history, with the running version marked.
2. They tap Roll back on the previous version.
3. The confirmation lists the commits being undone and any format warning.
4. They confirm; the page shows the older commit as running, and a rebuild if one was queued.
5. Later they tap Deploy now, and the site returns to `origin/main`.

## Success criteria

- `AppUpdateTest` covers rollback success, a conflicting local edit, a commit outside the history, a held lock and the format warning. Each refusal leaves `HEAD` and the local edits unchanged.
- `DeployPageTest` shows the history and rejects a rollback from a non-operator or a cross-origin request.
- The maintainer's prod replay on the iPhone passes the evidence in Known user and environment.

## Enhancement requests

- 2026-11-20, maintainer: "what about schema changes?" Handled in Step 1 and carried into the core requirements and risks here.

## Study snapshots and approval

- 2026-11-20 UTC, Step 2 handoff: **0.65** projected total, up from 0.60. New understanding: seeding the history from the old log, and the positional inserts found in two stores. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `eb0f5177`. **Approved Step 2** by the maintainer, 2026-11-22.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
