> **Feature plan:** [Step 1](./deploy_rollback_step1_solution_assessment.md) · [Step 2](./deploy_rollback_step2_feature_description.md) · [Step 3](./deploy_rollback_step3_development_plan.md) · [Step 4](./deploy_rollback_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_rollback`; host ledger [deploy_rollback.md](../effort_ledger/deploy_rollback.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.65**

Concepts touched: [application update](../../concepts/application_update.md) (extended; Stage 5 adds deploy history and rollback to the entry); [read-model rebuild](../../concepts/read_model_rebuild.md) (used unchanged). Feedback: none.

Change since Step 2 approval: the "future column breaks old code" risk is closed. PR #356 named the columns in those inserts and added the `CONTRIBUTING.md` rule, so this cycle no longer edits `CONTRIBUTING.md`.

## Completion Contract

- Normal entry: Tools → Deploy on the iPhone, or `./v3 deploy --history` and `./v3 deploy --rollback=<commit>` over SSH.
- End-to-end outcome: the page lists the last 20 deployed versions with the running one marked. Roll back on an earlier one, then Confirm, makes the site serve that commit. `public/.htaccess` keeps its local edit, and the history gains a rollback entry.
- Recovery: a refused or failed rollback leaves `HEAD` and local edits as they were and says why. Deploy now returns to `origin/main`.
- Works without JavaScript: the confirmation is an ordinary page, and every action is a form post.
- Deployment: none beyond a normal deploy. No migration, no rebuild.
- Release: merged, then the maintainer rolls prod back one version and forward again from the iPhone, and `git status` over SSH still shows the `.htaccess` edit.

## Key Risks

- **High risk:** the local edit doesn't apply on the older commit. Impact: a half-rolled-back checkout or a lost `.htaccess` edit. Early validation: Stage 2 tests a conflicting edit before any page work. Mitigation: reuse `restoreAt()`, so a conflict returns to the starting commit with the edit intact.
- **High risk:** older code misreads newer records. Impact: the site looks wrong while rolled back. Early validation: Stage 3 tests the warning across a `SCHEMA_VERSION` change. Mitigation: the warning on the confirmation page, and Deploy now as the one-step undo.
- A rollback target the checkout can't reach. Impact: a reset to a commit outside `main`. Early validation: Stage 2 refuses any commit that is not in the history and an ancestor of `HEAD`.
- The celebration banner fires on a rollback. Early validation: Stage 4's page test.

## Stage 1

**Estimate: 0.10**

- Goal: every deploy that changes the commit is recorded, and the record can be read.
- Dependencies: none.
- Expected changes: `AppUpdate` appends one `Key: value` block to `state/logs/deploy_history.txt` on a `deployed` outcome (`Deployed-At`, `Action`, `Mode`, `Actor`, `Old-Commit`, `New-Commit`, `Subject`). New `history(int $limit = 20): array`, newest first. On first read with no file, seed it from the `deployed` entries in `app_update.log.1` and `app_update.log`. `scripts/deploy.php` gains `--history`.
- Verification approach: `AppUpdateTest`: a deploy adds one entry; an up-to-date or refused attempt adds none; seeding from an old log; `--history` output.
- Estimate details (extended mode only): driver: one writer, one reader, one seed; uncertainty: low.
- Risks or open questions:
  - Impact: old log entries lack a field. Early warning: the seed test uses a block without `Read-Model`. Mitigation: seed only blocks with both commits; skip the rest.
- Canonical components/API contracts touched: `appendAttempt()`'s block format and `latestAttempt()`'s parser (reused; the parser is shared, not copied), `scripts/deploy.php` options.

## Stage 2

**Estimate: 0.20**

- Goal: `./v3 deploy --rollback=<commit>` returns the checkout to an earlier deployed commit, safely.
- Dependencies: Stage 1.
- Expected changes: `AppUpdate::rollback(string $commit, string $actor, string $mode, int $timeoutSeconds = 60): array`, returning the same shape as `deploy()`. It takes the lock, checks the branch, and refuses a commit that is not in the history, not an ancestor of `HEAD`, or already running. It sets local edits aside, resets `main` to the commit, puts the edits back, and on a conflict returns to the starting commit. It then runs the read-model check, appends an attempt (`Mode` as given, outcome `rolled_back`) and a history entry (`Action: rollback`). It never fetches, so it works offline.
- Verification approach: `AppUpdateTest`: success; a kept local edit; a conflicting local edit leaves `HEAD` and the edit unchanged; unknown commit; non-ancestor; held lock; wrong branch; a later `deploy()` fast-forwards back.
- Estimate details (extended mode only): driver: the stash cases, each with its own refusal; uncertainty: medium.
- Risks or open questions:
  - Impact: the first High risk above. Early warning: the conflicting-edit test. Mitigation: `restoreAt()`.
- Canonical components/API contracts touched: `acquireLock()`, `trackedChangedFiles()`, the stash handling and `restoreAt()` from `runDeploy()` (the shared part is extracted, not duplicated), `checkReadModelWithNewCode()`, `appendAttempt()`.

## Stage 3

**Estimate: 0.10**

- Goal: before rolling back, the operator sees what it undoes and whether it crosses a format change.
- Dependencies: Stage 2.
- Expected changes: `AppUpdate::rollbackPreview(string $commit): array` with the commits being undone (same shape and limit as incoming commits) and `warnings`: one each when the range changes `ReadModelMetadata::SCHEMA_VERSION`, `docs/specs/`, or `src/ForumRewrite/Canonical/`. The CLI prints both before it rolls back.
- Verification approach: `AppUpdateTest`: the commit list; each warning on and off, including a fixture that bumps `SCHEMA_VERSION`.
- Estimate details (extended mode only): driver: three path checks over one diff; uncertainty: low.
- Risks or open questions:
  - Impact: the second High risk above. Early warning: the `SCHEMA_VERSION` fixture. Mitigation: warn, never block.
- Canonical components/API contracts touched: `incoming()`'s commit listing (reused for the reverse range).

## Stage 4

**Estimate: 0.20**

- Goal: the Deploy page shows the history and rolls back in two taps.
- Dependencies: Stage 3.
- Expected changes: `templates/pages/deploy.php` gains a history table in the existing `codebase-facts` style: time, short commit linked as today, subject, and a Roll back button on entries that can be rolled back to. The button posts `action=rollback_preview`; the page then shows the commits undone, any warning, and Confirm and Cancel. Confirm posts `action=rollback`. `DeployController::submit()` adds both actions under the existing checks. `deploy_control.js` refreshes the history after a deploy. `deploy_celebrate.js` stays off for a rollback.
- Verification approach: `DeployPageTest`: history rendered; preview and rollback refused for a non-operator and cross-origin; a full rollback through the form. In a browser at 380px in Gloss, Gloss Dark and one pixel theme: the table fits, and the flow works with JavaScript off.
- Estimate details (extended mode only): driver: a second page state and its narrow-screen layout; uncertainty: medium.
- Risks or open questions:
  - Open question: the button label. The plan uses "Roll back" on the row and "Roll back to <commit>" on Confirm.
- Canonical components/API contracts touched: `codebase-facts` table, the commit links from `commitLinkBase()`, the incoming-commit list markup, `submit()`'s action match, `publicState()`.

## Stage 5

**Estimate: 0.05**

- Goal: the feature is documented and proven on prod.
- Dependencies: Stage 4.
- Expected changes: `docs/concepts/application_update.md`, `docs/runbooks/production_deploy.md` and `docs/reference/v3_cli.md` describe the history and rollback. Final diff review.
- Verification approach: links resolve; `./v3 test --smoke AppUpdateTest DeployPageTest`. Then the maintainer's prod replay from the Completion Contract; until it passes, the cycle is technically complete with production acceptance pending.
- Estimate details (extended mode only): driver: three short doc edits; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the concept entry and the runbook's "Deploy from the site" section.

## Study snapshot

- 2026-11-22 UTC, Step 3 handoff: **0.65** projected total, unchanged from Step 2. Stage work: 0.10, 0.20, 0.10, 0.20, 0.05 (not summed). Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `d9e9a91a`. **Approved Step 3** by the maintainer, 2026-11-22.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
