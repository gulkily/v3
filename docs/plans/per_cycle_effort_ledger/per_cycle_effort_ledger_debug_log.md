# per_cycle_effort_ledger debug log

2026-11-15T03:20Z — closeout

- The Railspike effort contract assumes one host ledger file; with many concurrent one-shot PRs that file is a guaranteed conflict point. Splitting by cycle ID fits its "merge by cycle/checkpoint" wording without changing vendored text.
- `git merge-tree --write-tree` honors `.gitattributes` from the merged trees, which made the union attribute easy to verify before pushing.
- The plans index `docs/plans/README.md` is the other shared append point (5 of 10 open PRs conflicted there on 2026-11-14); union is unsafe for it because entries get edited and removed.
