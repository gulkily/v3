> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Step 1 v4 approved; scope remains v4.

## Problem

Updating independent clones repeatedly interrupts work in persistent IntelliJ projects. Operators need one way to preview and apply routine updates while preserving unfinished work and recognizing cases requiring separate migration.

## User Stories

- As the operator, I want to update my registered clones from IntelliJ's terminal so that I can keep trusted projects open without repeating Git steps in each window.
- As the operator, I want a result and next action for each checkout so that skipped work, conflicts and rewritten history are understandable and recoverable.

## Core Requirements

- Use an explicit local registry of paths, expected repository/source, update target and role; validate identity and group shared Git storage. Include only selected checkouts; test/server instances require deliberate inclusion. Keep local paths/configuration private and stable.
- Fetch and preview each selected checkout against an identified source revision without changing its checked-out branch. Explain that fetching updates remote-tracking refs. Report fast-forward, merge, already current, locally ahead, dirty/skipped, operation in progress, unavailable/mismatched source, migration required, or excluded.
- Apply an explicit preview only after rechecking source, branch, HEAD and working state: fast-forward eligible main branches and merge fetched main into eligible feature branches. Preserve local commits; skip tracked/untracked work and existing Git operations. Changed conditions invalidate the affected preview; coordinate active agents/servers before apply, independently of cleanliness.
- Detect observed upstream rewrites and uncertain or unreconciled history before merging, including already-fetched rewrites; common ancestry alone does not establish safety. Hand these cases to separate migration recovery. Stop a conflicting checkout with resolve/abort guidance; report independent successes and partial completion honestly.
- Provide concise human output, JSON and useful help. State that only selected checked-out branches were updated; other branches, stashes and linked worktrees remain untouched. No push, reset, rebase, auto-stash, branch switching, migration, clone creation/deletion or IDE/trust changes.

## Delivery Scope and Completion Boundary

Work type: **application change — internal operator tooling**. Deliver registration/setup guidance, preview/apply and per-checkout recovery through the existing operator CLI, with relevant tests, CLI documentation and this cycle's plans/debug/study bookkeeping. No database, forum UI or automated IntelliJ changes. Branch-title plugin guidance remains optional and unverified; colors/task tables are not deliverables.

Release when an operator can configure selected clones, preview fetched updates, apply eligible changes and recover/retry partial failures through the documented entry, with the real-Git acceptance matrix passing. Rewritten-history migration is a separate authorized workflow, not part of this release or its success count.

## Known Environment and Flow

User uses IntelliJ IDEA 2023.3.6 with persistent projects over `/home/wsl` clones. Ordinary entry is the existing `./v3` CLI from IntelliJ's terminal; exact subcommand is settled in Step 3. Configure selected directories, coordinate coding sessions, preview, then apply and review results. For dirty/busy folders, finish or preserve work and retry; for conflicts, resolve or abort and preview again; for source failures, correct configuration/access and retry; for rewritten/uncertain history, follow the [migration runbook](../markdown_dependency_removal/history_rewrite_clone_sync.md) before retrying. Verify with a fresh preview plus reported branch/source alignment and working status. Acceptance must exercise this terminal workflow on this WSL host with disposable clones; fixtures elsewhere alone do not establish this environment's outcome. Do not mutate the live clone pool merely to test.

## Risks

- **Wrong source or shared storage:** could update unintended code. Validate registration, source normalization and linked-worktree grouping with disposable repositories before allowing updates.
- **Rewritten/already-fetched history:** an ordinary merge could restore removed history. Establish evidence for safe reconciliation and test ambiguous/rewrite cases before implementing dependent apply behavior; block uncertain cases.
- **Concurrent writers or dirty files:** preview can become stale and files can change under agents. Validate stale-state checks and operator session coordination early; recheck before each update and document coordination limits.
- **Conflicts or interrupted batches:** some folders may update while others stop. Validate per-folder recovery and retry using real conflicts/interruption; never report a partial batch as wholly synchronized.

## Shared Component Inventory

`v3` owns top-level command dispatch/help; extend it. `scripts/status.php` and `docs/reference/v3_cli.md` establish readable/JSON operator conventions; reuse those conventions while keeping instance status distinct from code synchronization. `scripts/railspike_sync.sh` synchronizes vendored workflow content, not checkout branches; retain its meaning. No existing cross-clone preview/registry or browser/API surface was identified; add a focused operator surface. Git remains authoritative for branch/worktree/operation state. Reuse the existing rewrite runbook for migration handoff.

## Success Criteria and Study Checkpoint

Every selected checkout receives a result; eligible routine updates preserve existing commits; dirty, busy, mismatched, stale and migration-required cases make no checked-out branch changes. Real-Git verification covers fast-forward, ahead/diverged branches, conflicts, interrupted operations, rewrites before/after fetch, source mismatch and shared storage; on-host terminal acceptance demonstrates setup, apply and recovery. Prior manual migration evidence is not a test pass for this future command.

2026-10-23T08:18:05+00:00; Step 2 total projection **unavailable pending exact frozen R1 feedback recovery**, preserving v1–v4 snapshots. Original baseline `19f1066aac81fe417d88a10efa9c756125199af5`; current rewritten checkout `1a358bc9be3cdb725277c792625a678e7bc85436`; R1/P2, Codex single exposed rater, prior trial and planning exposure disclosed. Total scope includes planning, tooling, verification, recovery and documentation; excludes migration automation. No measured effort or accepted outcome claimed. Ledger bookkeeping is included in delivery scope.

Request disposition: “Approved Step 1” / record v4 approval and draft Step 2 / Step 2 and implementation unapproved / assessment approval recorded above.

**Approved Step 2** received from the user; recorded 2026-10-23T09:50:06+00:00. Registry/mockup clarification is an in-scope refinement of the approved explicit-registry and preview/apply requirements; illustrative commands are finalized in Step 3. No automatic directory discovery is included. Implementation remains unapproved.
