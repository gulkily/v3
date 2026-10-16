# Project Railspike workflow

New work follows [Railspike](../railspike/RAILSPIKE.md). Its instructions are
included in ordinary clones at `docs/railspike/` as a **Git subtree**. This
project does not use the submodule installation/update commands in the upstream
README. Plans belong in this project's `docs/plans/`, outside the vendored tree.

## Provenance

- Source: [gulkily/railspike](https://github.com/gulkily/railspike), branch `master`.
- Initial reviewed revision: `95c435b82bf66c25bde834cbedfed8c54c361863`.
- The full upstream tree, including its license, is preserved without local edits.
- Subtree squash commits record later upstream revisions in `git-subtree-split`.
  Review those revisions and changes when updating. Keep project guidance here,
  outside `docs/railspike/`, to avoid conflicts with upstream instructions.

## Start or resume work

For new work, ask for the appropriate step of `docs/railspike/RAILSPIKE.md`.
Follow its approval gates, debug-mode state, and organization rules. After Step 3
approval and branching, new cycles move into their feature folder before the
planning commit, with navigation and the planning index updated.

The [FDP reference](../fdp/FEATURE_DEVELOPMENT_PROCESS.md) is frozen for cycles
already started under FDP, including this migration. Their approvals, data,
and historical documents remain valid. Finish those cycles under their approved
process unless the user explicitly replans and approves a transition. Do not
bulk-move or rewrite old plans. Legacy FDP links remain readable and identify
the transition; they do not govern new work.

## Update commands

```bash
./v3 railspike sync
./v3 railspike sync --repository-root=/path/to/another/migrated/repository
```

`./v3 fdp sync` is a deprecated alias with the same options. It updates Railspike,
never the frozen FDP reference. See the [CLI reference](v3_cli.md#synchronize-railspike-workflow-documentation).
The command requires a clean repository root, validates the subtree metadata,
and fetches `master`. It rejects an unexpected `railspike` remote unless you
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
git fetch https://github.com/gulkily/railspike.git master
git diff HEAD:docs/railspike FETCH_HEAD
git subtree pull --prefix=docs/railspike https://github.com/gulkily/railspike.git master --squash
```

Do not run the pull until existing work is committed or otherwise preserved.
Check the resulting diff, instruction links, and ordinary-clone availability.
Keep the frozen `docs/fdp/` tree unchanged.

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
