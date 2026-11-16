# merge_217_fix debug log

- The repository rule that every committed change runs as a Railspike cycle was applied after the PR opened, because `main` was broken and the fix went out first. This record and the ledger entry were added to the same open PR.
- `git show --cc <merge>` was the quickest way to find the bad resolution: it lists only hunks that differ from both parents.
