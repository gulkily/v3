# Project Railspike workflow

New work follows [Railspike](../railspike/RAILSPIKE.md). Its instructions are
included in ordinary clones at `docs/railspike/` as a **Git subtree**. This
project does not use the submodule installation/update commands in the upstream
README. Plans belong in this project's `docs/plans/`, outside the vendored tree.

## Provenance

- Source: [rlspk/railspike](https://github.com/rlspk/railspike), release tags, pinned at `v0.2.0-beta.1` (migrated from gulkily/railspike `master`; see docs/railspike/RAILSPIKE_SOURCE.md).
- Initial reviewed revision: `95c435b82bf66c25bde834cbedfed8c54c361863`.
- The full upstream tree and license are retained, with the local experimental estimation patch described below. The upstream Ref/Commit identifies the imported baseline, not the locally patched contents.
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
./v3 railspike sync                      # pinned v0.2.0-beta.1: no-op when installed
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
