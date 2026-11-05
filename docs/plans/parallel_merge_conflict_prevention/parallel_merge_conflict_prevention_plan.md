# Parallel agent merge-conflict prevention — development plan

Status: proposed; documentation-only planning work. No implementation, process-rule change, or PR #24 conflict resolution is claimed.
Date: 2026-11-05
Trigger: [PR #24 — Fix Chicago post action styling](https://github.com/gulkily/v3/pull/24)

## Problem and evidence

PR #24 is a draft from `ChinaEidolon/v3:fix/chicago-post-actions`, based on upstream commit `8bc40af67d14c3b76f8ec057edac1bc084a151be`. When inspected on 2026-11-05, GitHub reported `mergeable: false`, and `main` had advanced 29 commits from that base.

Both branches changed three files:

- `docs/plans/effort_estimation_trial.md`: the Chicago cycle and newly merged Calendar cycle both inserted records immediately after `# Effort estimation trial ledger`. This is a direct competing edit at the same hunk and the clearest conflict cause. Both entries are valid and must survive integration.
- `docs/plans/README.md`: both sides added plan links, at different locations in the file. Shared-file contention exists, but the observed hunks do not themselves establish a conflict.
- `tests/run.php`: both sides registered new tests, in different locations in the array. Shared-file contention exists, but the observed hunks do not themselves establish a conflict.

The feature's `public/assets/theme-chicago.css` change did not overlap with the files changed on `main` since the PR base. The immediate failure is principally a coordination/bookkeeping problem, not evidence of incompatible Chicago styling.

These findings come from GitHub's PR metadata, changed-file patches and the comparison of `8bc40af6...main`. A complete local three-way merge was not performed, so the exact final set of conflict markers remains to be confirmed.

## Desired outcome

Independent agents can produce reviewable feature PRs without repeatedly conflicting over shared documentation indexes, study ledgers or the test registry. Any unavoidable conflict is detected and resolved on the contributing branch before the PR is handed off for review. Concurrent contributions are never silently discarded.

## Proposed work

### Stage 1 — immediate PR #24 recovery

1. Fetch current upstream `main` in the contributor checkout and merge it into `fix/chicago-post-actions` without rewriting the published PR history.
2. Resolve the effort ledger by retaining the Calendar and Chicago entries, including all historical checkpoints and links. Preserve both sides of any other shared-file changes.
3. Run `git diff --check`, `./v3 feedback check`, and the smoke set plus affected tests, respecting the project's existing test policy. Report environment-dependent checks honestly.
4. Push to `ChinaEidolon/v3:fix/chicago-post-actions`; verify GitHub now reports the PR mergeable. Do not merge or deploy as part of this stage.

### Stage 2 — decentralize study records

1. Treat each cycle's own `docs/plans/<slug>/` artifact as the authoritative record of its P2 checkpoints.
2. Replace mandatory per-cycle edits at the top of `docs/plans/effort_estimation_trial.md` with a derived or integration-maintained index. Preserve all existing history, R1 references, score revisions, incomplete outcomes and stable IDs.
3. Decide whether to generate a summary from cycle artifacts or maintain a manifest with a collision-free one-file-per-cycle convention. Prefer deterministic regeneration and test that the output is stable.
4. Update `AGENTS.md` and the study's continuing-enrollment instructions together, so they do not issue contradictory commands.

### Stage 3 — reduce shared-index edits

1. Introduce deterministic discovery or generation for the plan index where feasible. Retain human-friendly descriptions and stable links.
2. Evaluate discovering `tests/*Test.php` automatically rather than requiring every feature PR to edit `tests/run.php`. Preserve test bootstrap behavior, deterministic order, smoke selection, filtering, isolation and intentional exceptions.
3. Add targeted tests for the index generator and test discovery. Avoid unrelated test-runner refactors.

### Stage 4 — enforce integration readiness

Add a concrete pre-publication gate to `AGENTS.md`, `CONTRIBUTING.md`, and `docs/agents/thread_to_pr.md`:

> Before opening or updating a PR, fetch the latest target branch and check whether the proposed head merges cleanly. If not, integrate the current target branch into the feature branch, resolve conflicts while preserving independent changes, rerun relevant checks, and push the resolved branch. A PR with unresolved conflicts is not ready for review. Never resolve a shared-file conflict by blindly selecting one entire side. Do not force-push a branch under active review solely to make it current.

Implementation should use an actual merge attempt in a clean worktree or equivalent reliable merge-tree check, not merely compare changed filenames. Recheck immediately before final handoff; a later base-branch update may require another integration.

For contributor forks, push fixes to the PR head fork rather than assuming upstream push access. If permissions prevent this, explicitly hand off the exact conflicting files and resolution to the contributor.

## Acceptance criteria

- A controlled reproduction of PR #24's ledger collision demonstrates the existing failure.
- Concurrent sample cycles can each add their own records without editing the same authoritative ledger hunk; generated output is deterministic and preserves both entries.
- Plan navigation remains discoverable without routine competing manual index edits.
- New tests can be included without unsafe or surprising changes to test order, filtering, or discovery.
- A documented pre-PR check detects a deliberately conflicting branch and blocks a “ready” handoff until resolved.
- CI or local verification distinguishes a failed merge from ordinary test failures.
- Existing historical records and feedback references remain intact.
- The process does not authorize agents to merge, deploy, force-push reviewed history, or discard other agents' work.

## Risks and trade-offs

- Generated documents can produce noisy diffs if they are committed independently by feature branches. Prefer generation on demand or an integration-only update and test byte-for-byte determinism.
- Automatic test discovery can change execution order or include fixtures unintentionally. Verify current runner semantics before replacing explicit registration.
- Always merging `main` immediately before PR creation reduces stale conflicts but does not prevent a race after publication; mergeability must be rechecked at handoff and when the base changes.
- Rebasing a contributor's published branch can rewrite history and invalidate review context; default to a normal merge for existing PRs.
- Changes to Railspike's vendored subtree are unnecessary; host-project process changes belong outside `docs/railspike/`.

## Suggested implementation boundaries

Keep the recovery of PR #24 separate from the durable workflow refactor. The latter can be split into small reviewable PRs: (1) pre-PR mergeability instructions/check, (2) decentralized effort records/index, (3) plan index and test discovery. Each should use the existing smoke-plus-changed-tests policy and describe unverified acceptance honestly.

## Decisions needed before implementation

- Should the study ledger be generated on demand, generated only at integration, or retained as a manually curated summary with per-cycle source files?
- Should plan and test discovery be implemented together or independently?

Recommended default: per-cycle authoritative study files with a deterministic on-demand index; implement plan-index discovery and test discovery in separate changes.
