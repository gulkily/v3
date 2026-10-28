# Railspike installation record

Local record, not an upstream file.

- Source: https://github.com/rlspk/railspike.git
- Ref: tag v0.4.0
- Commit: ea1ad5b524c2b447d3e61b15ccd3cd412ce6ecef
- Previous archive origin: `git archive` of v0.2.0-beta.1, fetched 2026-10-16; Git metadata excluded.
- Installation type: Git subtree path `docs/railspike`, updated with `git subtree merge --squash` on 2026-10-28.
- Migrated from: https://github.com/gulkily/railspike.git at bf5cd25e1235f8dc661c121c0e6cab5f07132299 (`master`); the installed copy was byte-identical to that revision, with no local edits.
- Retained local deviations: this installation record only; the prior effort-estimation patch is included upstream in this release.
- Removed upstream (still in Git history): `.nojekyll`, `index.html`, `railspike-logo.png`, `scripts/`, `docs/plans/`, `docs/maintainers/`, `docs/research/`.

- Previous installed release: v0.3.0-beta.4 (`f8c8d2588a4a849a12acc7c425d99f533e218f8d`).
- Upgrade verification: all 18 upstream product files match v0.4.0 byte-for-byte; the prior installed state remains recoverable from the upgrade subtree merge's first parent.
- Canonical source retained; the legacy configured remote was migrated during an earlier upgrade.
