# Project Railspike workflow

New work follows [Railspike](../railspike/RAILSPIKE.md). Its instructions are
included in ordinary clones at `docs/railspike/` as a **Git subtree**. This
project does not use the submodule installation/update commands in the upstream
README. Plans belong in this project's `docs/plans/`, outside the vendored tree.

## Provenance

- Source: [rlspk/railspike](https://github.com/rlspk/railspike), release tags, pinned at `v0.4.0` (migrated from gulkily/railspike `master`; see docs/railspike/RAILSPIKE_SOURCE.md).
- Initial reviewed revision: `95c435b82bf66c25bde834cbedfed8c54c361863`.
- The full upstream tree and license are retained. The historical local effort-estimation patch described below is now included upstream; only the host installation record differs from the release.
- Subtree squash commits record later upstream revisions in `git-subtree-split`.
  Review those revisions and changes when updating. Keep project guidance here,
  outside `docs/railspike/`, to avoid conflicts with upstream instructions.

## Start or resume work

For new work, ask for the appropriate step of `docs/railspike/RAILSPIKE.md`.
Follow its approval gates, debug-mode state, and organization rules. After Step 3
approval and branching, new cycles move into their feature folder before the
planning commit, with navigation and the planning index updated.

The legacy `docs/fdp/` tree has been removed after the Railspike migration.
Historical plans retain their original FDP wording, approvals, and paths; those
paths refer to the former tree rather than current documentation. To inspect the
frozen workflow for an existing cycle, use Git history, for example:

```bash
git show 213cdea966f43f851c3d11208890b599012fd239:docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md
```

Existing cycles keep their approved process unless explicitly replanned and
approved. Do not bulk-move or rewrite old plans.

## Local experimental effort-estimation patch

The [effort estimation trial](../plans/effort_estimation_trial.md) adds an opt-in contract in [vendored Railspike](../railspike/RAILSPIKE.md#experimental-effort-estimation) and hooks in its eight Step 1–4 instruction files. Project-specific sources, hashes, candidates and outcomes remain in the host ledger; selected feature artifacts carry snapshots. Selection must be explicit, and the setup cycle itself is not one of the six study outcomes. Unrelated cycles need no estimates.

This is an intentional local product patch, not a new upstream release. Its scope is `docs/railspike/RAILSPIKE.md` and the eight Markdown files in `docs/railspike/docs/workflow/`. Keep the recorded upstream provenance unchanged. The setup's [implementation summary](../plans/effort_estimation_trial/effort_estimation_trial_step4_implementation_summary.md) records the stage commit for later roll-up. Plans, diagnostics and study data stay outside the vendored tree.

Before a future subtree update, inspect and preserve this patch in branch history; compare the incoming workflow for equivalent estimation behavior and resolve any conflicts deliberately. Do not silently drop the experiment or duplicate its contract. Recheck opt-in behavior, checkpoint hooks and relative links after updating. Upstream submission/roll-up and any subtree update are separate authorized work; this trial does not perform them.

## Update commands

```bash
./v3 railspike sync                      # pinned v0.4.0: no-op when installed
./v3 railspike sync --ref=<newer-tag>    # deliberate upgrade
./v3 railspike sync --repository-root=/path/to/another/migrated/repository
```

`./v3 fdp sync` is a deprecated alias with the same options. It updates Railspike,
never the retired FDP workflow. See the [CLI reference](v3_cli.md#synchronize-railspike-workflow-documentation).
The command requires a clean repository root, validates the subtree metadata,
and fetches the explicit release tag (never a branch). It refuses a target that does not descend from the commit recorded in `docs/railspike/RAILSPIKE_SOURCE.md` (e.g. `main`, which is currently older than the beta). It rejects an unexpected `railspike` remote unless you
explicitly select a source with `--remote-url`. That selection is recorded only
after success; forks must retain the recorded upstream history. No-op updates
create no new commits. Failed validation does not modify files or configuration.
Fetches may update Git objects and `FETCH_HEAD`, even if later validation fails.

Review upstream changes before invoking sync: it creates commits without an
interactive approval prompt. To inspect first, use the fetch/diff commands below,
then run sync or the manual pull on the reviewed source. If upstream changes
between review and fetch, review the resulting commit before integrating it.

## Manual subtree update

Use a clean working tree on a maintenance branch. Review the upstream revision
and its diff before incorporating it. A subtree update creates Git commits;
it does not publish or deploy anything.

```bash
git status --short
git fetch --no-tags https://github.com/rlspk/railspike.git refs/tags/<tag>
git diff HEAD:docs/railspike FETCH_HEAD   # review; target must descend from the recorded commit
git subtree pull --prefix=docs/railspike https://github.com/rlspk/railspike.git <tag> --squash
# then update Ref/Commit in docs/railspike/RAILSPIKE_SOURCE.md
```

Do not run the pull until existing work is committed or otherwise preserved.
Check the resulting diff, instruction links, and ordinary-clone availability.
The retired `docs/fdp/` tree does not need to be restored for updates.

## Recovery and older target repositories

For a failed fetch, check connectivity/source and retry; no working files should
have changed. For a subtree merge conflict, inspect `git status`, resolve and
commit deliberately, or run `git merge --abort` in the target repository to
restore its pre-update state. Never discard local work with a hard reset.

A target without the Railspike subtree must first receive the reviewed migration
commits through the project's normal Git integration. Missing subtree metadata
requires restoring the import history; copying files alone is insufficient.
Do not manufacture metadata from old FDP squash commits. Keep full subtree
history available in maintenance clones. This procedure does not automatically
migrate other repositories.

## Forum handoffs

New handoff drafts name Railspike and point to its workflow entrypoint. The
`railspike` board tag or thread label is eligible alongside existing development
tags, including `fdp`. Thread-label eligibility still applies only to the root
post, and existing localhost and user-approval requirements remain in force.
Existing drafts are not regenerated. The `fdp_step1` storage/API field remains
for compatibility with both PHP previews, the browser preview, and older clients;
this terminology change requires no data migration. Runner execution and approval
behavior are unchanged.

## Phone intake deployment

The operator-only phone workflow uses the instance task queue and a separate read-only Codex assessment checkout. See [deployment, Codex configuration, acceptance, and recovery](../runbooks/phone_site_development.md). It stops after revision-bound Step 1 approval; legacy localhost handoffs remain separate.

## Continuing collection update — 2026-10-21

Standing enrollment under [protocol P2](../plans/effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2) supersedes the individual-selection-only wording above for future cycles. All future Railspike cycles join the same `railspike-effort` study; R1 and historical records are preserved. The installed experiment contract now recognizes standing enrollment and worktree deduplication. This remains a local patch pending the next minor upstream beta; upstream Ref/Commit metadata has not changed. There was no subtree sync, upload or application change.

## Upgrade — 2026-10-21

Upgraded to `v0.2.1-beta.1` (`b6c399424f75dc2e94af496411467480487cc319`) through a squash subtree merge. The release incorporates the installed effort-estimation contract and all eight checkpoint instruction files unchanged, so no local experiment patch remains. The historical patch notes above describe the pre-upgrade installation. Resolved the two README conflicts to the reviewed release versions; the product tree matches upstream except for the host installation record. Updated the CLI default pin and source record, and migrated the legacy `railspike` remote to `https://github.com/rlspk/railspike.git`.

Verification: all 17 upstream product files match the release byte-for-byte; `php tests/run.php RailspikeSyncCommandTest RailspikeWorkflowTest` passed all 20 tests; shell syntax and `git diff --check` passed. No UI files changed.

## Upgrade — 2026-10-24

User authorization: “Please upgrade to 0.3.0-beta.1.” This explicitly approves the Railspike dependency update from v0.2.1-beta.1 to v0.3.0-beta.1. This routine pinned update uses the direct maintenance path, without a new Railspike planning cycle.

Source: https://github.com/rlspk/railspike.git; MIT license; delivery: existing Git subtree at `docs/railspike/`. Verified product commit `c336536f88d1ce471b4f582a7d87203864f8526e` descends from the prior installed revision. Reviewed all upstream changes: 11 existing Markdown files, 116 insertions and one deletion (roughly 15 KB added text), with no new files, transitive dependencies, runtime packages, manifests or locks. The release adds optional option/stage estimates and exact frozen-reference recovery. The reduced-scope alternative was retaining the previous beta. Maintenance remains deliberate tag review, subtree sync and provenance verification.

Updated the CLI default pin, help, reference and affected test assertion. Extended estimates remain off absent explicit cycle activation or host policy; existing standing study enrollment is preserved. The clean maintenance worktree keeps unrelated host edits intact. The prior installed state remains recoverable from the subtree merge's first parent.

Verification: all 17 upstream product files match the release byte-for-byte; `php tests/run.php RailspikeSyncCommandTest RailspikeWorkflowTest` passed all 20 tests; shell syntax and `git diff --check` passed. Actual dependency delta matches the approved inventory: one existing instruction dependency upgraded, zero added packages or transitive dependencies. UI/runtime/deployment checks are inapplicable to this instruction-only upgrade.

## Upgrade — 2026-10-25

User authorization: “please upgrade to latest railspike beta.” Resolved the latest
upstream beta tag to `v0.3.0-beta.4`, commit
`f8c8d2588a4a849a12acc7c425d99f533e218f8d`, and verified descent from the
installed v0.3.0-beta.1. This routine dependency update uses the direct maintenance
path, without a new Railspike cycle. Uncommitted README planning and study records
remain untouched in the original worktree.

Inventory: existing MIT-licensed dependency from https://github.com/rlspk/railspike.git,
through the existing `docs/railspike/` Git subtree; 17 product files, about 138 KB.
The reviewed update changes 10 Markdown files (149 insertions, 45 deletions),
with zero new product files, packages, transitive dependencies, manifests or locks.
The reduced-scope alternative was retaining beta 1. Maintenance remains deliberate
tag review, subtree sync, provenance verification and focused host checks.

Beta 2 enables extended estimates for new enrolled cycles; beta 3 improves estimate
presentation and contribution attribution; beta 4 uses one attribution footer for
a Step 3 plan generated by one agent in one pass, with later stage revisions
attributed separately. Existing cycle modes and missing historical values are
preserved. Standing P2 enrollment and R1 are unchanged. Earlier upgrade notes
are historical, not a host policy overriding the new-cycle default.

Updated the CLI default pin, help, reference and affected assertion. The previous
installation is recoverable from the subtree merge's first parent. No deployment
or bulk rewrite of existing plans is included.

Verification: all 17 product files match the pinned upstream commit byte-for-byte
(138,452 bytes). `php tests/run.php RailspikeSyncCommandTest RailspikeWorkflowTest`
passed 20/20; shell syntax and `git diff --check` passed. The actual dependency
delta matches the inventory above. UI/runtime/deployment checks are inapplicable
to this instruction update.

*— Codex · 2026-10-25 UTC*

## Upgrade — 2026-10-26

Upgraded from `v0.2.1-beta.1` to `v0.4.0-beta.1` (`0dcab22380874e53dae641b3d82a24c703241ac9`) with `./v3 railspike sync --ref=v0.4.0-beta.1` on the maintenance branch `railspike-v0.4.0-beta.1`. The merge was clean; no local product patch existed to preserve. Updated the CLI default pin, its usage text and test, the CLI reference, and the source record.

What changed upstream (13 commits):

- **One-shot mode** (`docs/railspike/docs/workflow/one_shot.md`): explicitly authorized, bounded work runs in one evolving record (`docs/plans/{feature}/{feature}_one_shot.md`) without intermediate step approvals, normally with one completion commit. Staged cycles are unchanged. Railspike now starts by choosing the execution mode.
- **Agent attribution**: authored or revised plan sections are signed with an italic line (agent name, UTC date, and verified model/settings only).
- **Effort estimation**: extended estimates (every Step 1 option and every Step 3 stage) are enabled by default for new enrolled cycles; estimates are shown as bold `Estimate:` and `Cycle estimate:` lines; one-shot cycles have their own checkpoints; frozen references are recovered from host-local snapshots.

Effect on the `railspike-effort` study: standing enrollment under protocol P2 still applies, now to one-shot cycles as well. New enrolled cycles get extended estimates unless the user overrides it; existing cycles keep their recorded state.

Verification: installed product files match the tag byte-for-byte (the only difference is the host `RAILSPIKE_SOURCE.md`); the Railspike sync and workflow tests are recorded in the commit that updated the pin.

## Upgrade — 2026-10-28

Authorization: “Please upgrade railspike to latest.” Resolved the latest release
from upstream tags to v0.4.0, commit ea1ad5b524c2b447d3e61b15ccd3cd412ce6ecef,
and verified descent from installed v0.3.0-beta.4. Routine direct maintenance,
without starting a new Railspike cycle or changing existing cycle modes.

Inventory: Existing MIT-licensed instruction dependency from
https://github.com/rlspk/railspike.git, delivered through the existing Git subtree
at docs/railspike/. Eight Markdown files change (206 insertions, 26 deletions),
including the new one-shot workflow; 18 upstream files total. No added packages,
transitive dependencies, manifests or locks. The reduced-scope alternative was
retaining beta.4. Maintenance remains reviewed tag updates and provenance checks.

Changes: Optional explicitly authorized one-shot execution with a consolidated
record, matching effort checkpoints, revised welcome guidance and uninstall
instructions. Host subtree update instructions remain authoritative over upstream
copy/submodule examples. Existing AGENTS.md guidance, standing P2 enrollment,
frozen R1 references and historical cycle settings remain intact. Updated the
CLI default pin, top-level help, reference and affected test expectation.

Recovery: Prior installation is available from the subtree merge's first parent.
No deployment or remote publication is included. Upgrade commits are on
maintenance/railspike-v0.4.0.

Verification: All 18 upstream files match the pinned revision byte-for-byte
(163,487 bytes). RailspikeSyncCommandTest and RailspikeWorkflowTest passed 20/20.
Shell syntax, CLI help and git diff --check passed. Local document link paths
in the entrypoint, one-shot workflow, effort guide, index and welcome resolve;
anchors and remote links were not checked. The initial link scan included fenced
template examples; excluding those corrected that check. Actual dependency delta
matches the inventory above. UI, deployment and full application-suite checks
are inapplicable to this instruction and CLI-pin update.

*— Codex · 2026-10-28 UTC*

Merge handoff — 2026-10-28: User authorized merge and push. Integrated remote
main at 631ebab0a, retaining its beta.1 upgrade history and both branches' planning,
study and UI verification records; resolved current pins/product to v0.4.0.
All 18 product files still match upstream. Focused Railspike checks passed 20/20
again; startup dependency regressions, shell syntax and staged whitespace checks
passed. Full application suite was not rerun. No new dependency changes.
