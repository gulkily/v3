# Railspike installation record

Local record, not an upstream file.

- Source: https://github.com/rlspk/railspike.git
- Ref: tag v0.3.0-beta.3
- Commit: 690c596307b2087515b7c258008e5835af89048b
- Previous archive origin: `git archive` of v0.2.0-beta.1, fetched 2026-10-16; Git metadata excluded.
- Installation type: Git subtree path `docs/railspike`, updated with `git subtree merge --squash` on 2026-10-24.
- Migrated from: https://github.com/gulkily/railspike.git at bf5cd25e1235f8dc661c121c0e6cab5f07132299 (`master`); the installed copy was byte-identical to that revision, with no local edits.
- Retained local deviations: this installation record only; the prior effort-estimation patch is included upstream in this release.
- Removed upstream (still in Git history): `.nojekyll`, `index.html`, `railspike-logo.png`, `scripts/`, `docs/plans/`, `docs/maintainers/`, `docs/research/`.

- Previous installed release: v0.2.1-beta.1 (`b6c399424f75dc2e94af496411467480487cc319`).
- Upgrade verification: all 17 upstream product files match v0.3.0-beta.1 byte-for-byte; the prior installed state remains recoverable from the upgrade subtree merge's first parent.
- Canonical source explicitly selected during this upgrade to migrate the legacy configured remote.
