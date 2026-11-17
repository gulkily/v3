# Nav cached copy debug log

## 2026-11-17T06:00Z — One-shot closeout

**Necessary/useful instructions**
- `implementation_standards.md` risk handling before dependent work: reading `version_check.js` first found the pending-version redirect, which would have flashed a page the maintainer had just ruled out. Retain.
- `AGENTS.md` "Pages must look final on first paint" and client-side viewer UI: made copies close to viewer-independent, which kept the design small.
- Planning commit gate: committed `6e9f9d01` before edits.

**Redundant/unnecessary instructions**
- Navigation icons: no destination changed.

**Inapplicable instructions**
- Read-model schema, retraction, third-party code.

**Uncertain assessments**
- Branching from an unmerged predecessor (`feat/nav-quick-wins`): `AGENTS.md` says follow-ups start from `origin/main`, but that rule covers feedback on merged PRs; this cycle extends unmerged code, so it was stacked and the PR says to merge #291 first.
- The `?__v` redirect is replaced by the notice for every page under the flag, not only copies; the page cannot tell synchronously whether it came from a copy.
- One unexplained test failure in nine runs (record, history).
