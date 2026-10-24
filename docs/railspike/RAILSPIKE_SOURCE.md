# Railspike installation record

Local record, not an upstream file.

- Source: https://github.com/rlspk/railspike.git
- Ref: tag v0.3.0-beta.1
- Commit: c336536f88d1ce471b4f582a7d87203864f8526e
- Previous archive origin: `git archive` of v0.2.0-beta.1, fetched 2026-10-16; Git metadata excluded.
- Installation type: Git subtree path `docs/railspike`, updated with `git subtree merge --squash` on 2026-10-24.
- Migrated from: https://github.com/gulkily/railspike.git at bf5cd25e1235f8dc661c121c0e6cab5f07132299 (`master`); the installed copy was byte-identical to that revision, with no local edits.
- Retained local deviations: this installation record only; the prior effort-estimation patch is included upstream in this release.
- Removed upstream (still in Git history): `.nojekyll`, `index.html`, `railspike-logo.png`, `scripts/`, `docs/plans/`, `docs/maintainers/`, `docs/research/`.

- Previous upgrade verification: installed product files matched tag v0.2.1-beta.1 byte-for-byte; that prior state is recoverable from the first parent of merge commit 97d47b97.
- Previous Railspike remote: https://github.com/gulkily/railspike.git; migrated to the recorded source for this upgrade.

- 2026-10-24: explicitly requested upgrade from v0.2.1-beta.1; official v0.3.0-beta.1 changes 11 existing Markdown files, no additional components or transitive dependencies. Host CLI pin and focused assertion updated alongside the subtree.
- Current upgrade verification: all 17 upstream files match v0.3.0-beta.1 byte-for-byte; focused sync/workflow tests passed 20/20, shell syntax and diff checks passed.
