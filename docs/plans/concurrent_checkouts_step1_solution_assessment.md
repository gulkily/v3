> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; standing [P2 enrollment](./effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Original Query

We're running what seems like 7–8 local copies of this repo just so that we can work on stuff concurrently. It's starting to get hard to track. How can we make this easier? I've found workspaces to be confusing in the past.

Clarification: “Worktree is what I meant as confusing.”

## Problem Statement

Concurrent checkouts have opaque slot names and no single view connecting each folder to its task, branch, changes and running preview.

## Option A: Keep clones and add a shared overview

Retain ordinary clones; name new task folders descriptively and provide one command listing each registered folder, task, branch, working changes and known preview URL, with explicit unknown values.

- Pros: Familiar folder workflow; can cover existing clones immediately without moving active work.
- Cons: Independent Git histories still need fetching; registration and task ownership must stay current.

## Option B: Standardize task folders using worktrees

Use task-named Git worktrees with a similar overview and lifecycle helpers.

- Pros: Shared Git storage and branch inventory.
- Cons: User explicitly finds worktrees confusing; still needs task and runtime tracking. Not recommended given clarification.

## Option C: Keep fixed clone slots and a manual task list

Keep numbered clones and maintain a short table of slot, task, branch and preview URL.

- Pros: Minimal setup; no migration.
- Cons: Manual status goes stale; slot names remain hard to recognize.

## Recommendation and Vertical-Slice Viability

Choose **Option A**: begin with a read-only overview of existing clones and descriptive labels; use task names for future folders. Keep main and intentional test instances distinct from task clones. One task owns a folder until completion; cleanup checks local commits, uncommitted/untracked work and instance state before retirement. Existing active folders need not move. Preview URLs must be verified or marked unknown; separate runtime configuration/state remains necessary. Automated creation, migration and deletion can be deferred. The first slice is useful when an operator can identify and open the right checkout and preview from one overview.

Observed: six `/home/wsl/agent/v3-*` clones, main, two test checkouts and one linked worktree registered to main. Multiple clones have working changes; zero status rows does not prove disposable work. No runtime ownership or unique-commit audit yet.

## Request Disposition

Request: simplify concurrent work; clarification rejects worktree complexity / Disposition: recommend clone overview rather than worktree migration / Approval: assessment only; implementation unapproved / Outcome: existing folders preserved.

## Effort Snapshots

Intake disclosure and Step 1 handoff, 2026-10-23T06:09:41Z; scope v1; baseline `19f1066aac81fe417d88a10efa9c756125199af5`; R1/P2; Codex single exposed rater. Directory, branch/status inventory and workflow guidance were inspected before intake recording; no pristine prospective intake claim. User clarification arrived during assessment, before an earlier scope artifact or score existed. Total covers planning, read-only clone overview, verification and documentation; excludes migration/deletion and lifecycle automation. Numeric total unavailable pending exact frozen R1 source recovery; no substitute reference, measured effort or accepted outcome claimed.

Await **Approved Step 1** before Step 2.

## Revision v2 — Persistent IntelliJ projects

User clarified that IntelliJ is the preferred interface for files, plans and chat. Repeated setup consists of cloning, Codex trust/permissions, and opening/trusting a new IntelliJ project. The problem is persistent session identity and reuse, rather than clone storage.

Supersedes the v1 recommendation: choose **Option C, enhanced with permanent project colors and a shared overview accessible inside IntelliJ**. Reuse existing directory paths and open projects; do not rename/move them or create a folder for every task. Assign each a permanent slot/color; record current task, branch, plan and status separately. A shared, local overview can be exposed in each project via a local file link and pinned editor tab. Task changes update the overview; completed work must be preserved before branch reuse. Start a fresh task conversation when assigning a different task. Automated project creation, Git resets/deletion and trust changes are excluded. Pilot with two existing windows before expanding.

Concrete initial user actions: in IntelliJ's supported UI, right-click the main toolbar, choose Change Project Color, and assign a different color per project. If colors are hidden, enable Use project colors in main toolbar in Appearance settings. Keep an overview tab and the current plan available in each project. Exact UI availability depends on the installed version; existing local configuration directories do not establish the active IDE version. Official reference: https://www.jetbrains.com/help/idea/customize-actions-menus-and-toolbars.html#configure-the-toolbar-color . No IDE settings or active clones changed by this assessment.

Request disposition: “ok, how?” requests concrete steps for this approach; revise current assessment and supply manual guidance. No exact Step 1 approval or implementation approval received; no Step 2 artifact created.

### Revised study checkpoint

2026-10-23T06:27:07Z, Step 1 scope v2, original baseline/R1/P2, Codex single exposed rater. Preserve v1 snapshot above. Calibration report and diff are now present with matching frozen SHA-256 values; feedback source hash is `6f5a07426cf0624bde554b0d8034f32313481a0a8ca7387ce085386f2bdef31c`, differing from frozen R1 `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc`. Numeric projected total remains unavailable pending exact feedback recovery. Scope is persistent IntelliJ project identification and a local shared task overview, including setup/reuse guidance and verification; no clone lifecycle automation. No measured effort or accepted outcome.

## Revision v3 — Bring existing clones up to date

Current user intent: branch already identifies the task inside IntelliJ; expose it in the operating-system window title and remove repeated synchronization of existing clones. IntelliJ version is 2023.3.6. User clarified synchronization means bringing branches up to date and responded “Sounds great” to a previewable sync-all command. This supersedes color/task-table and new-folder recommendations; preserve prior snapshots as history.

### Option D: Previewable batch synchronization (recommended)

Keep all existing clones and IntelliJ projects. A single operator command previews selected checkouts, fetches their configured source, then on explicit application fast-forwards eligible main branches and merges the fetched main into eligible feature branches. It reports per-folder outcomes and recovery actions. Selection is explicit/configured; intentional test/server instances are excluded by default. Skip dirty folders and existing merge/rebase/cherry-pick operations; verify source identity and recheck branch/HEAD/worktree before mutation. A conflict stops that folder, preserves evidence, and reports how to resolve or abort; other independent folders may proceed. Apply between active coding sessions; a clean working tree alone does not establish that an agent is idle.

- Pros: Keeps trusted directories and persistent IDE sessions; removes repeated manual updates without history rewriting.
- Cons: Feature merges can conflict or create merge commits; requires clear source selection and coordination with active agents.

### Option E: Fetch and report only

Fetch each selected clone and show which branches need updates; leave all checked-out files untouched.

- Pros: Lower coordination burden; no merge conflicts introduced by the command.
- Cons: Leaves the recurring branch-update chore to the user.

### Current recommendation and completion boundary

Choose Option D with a preview before application and concise default output plus JSON per repository CLI guidance. A complete slice covers configured clone selection → preview/fetch → apply → per-folder result and conflict recovery, with real Git fixtures for clean, dirty, diverged, conflicting and interrupted states. No push, reset, rebase, branch switching, clone migration, automatic deletion or trust/configuration bypass. Initially target the agent clones after verifying common source; do not automatically include test/live instances or the main operator checkout. Decide precise CLI placement and preview/fetch semantics in subsequent planning. Installation of the branch-title plugin is user-side guidance, not an automated IDE modification; official source declares a compatible build range but this installation has not been tested.

Request disposition: “Sounds great” selects the proposed approach; this is recorded as direction agreement, not the exact required Railspike approval. Step 1 remains current; no implementation or Step 2 artifact.

### Scope v3 study checkpoint

2026-10-23T06:34:57+00:00; Step 1 handoff; stable ID `v3/concurrent_checkouts`, original baseline/R1/P2, Codex single exposed rater. Scope revised from identification/manual overview to previewable clone updates with conflict/recovery verification. Earlier v1/v2 retained. Exact frozen feedback remains unavailable as verified in v2; no numeric projection, measured effort or accepted outcome. No implementation approval.

Await **Approved Step 1** for this v3 scope before Step 2.

## Planning save authorization

User explicitly requested committing these records into main and restoring a clean worktree. This authorizes the planning save before the usual planning-commit gate; it does not approve Step 1 advancement or implementation.
