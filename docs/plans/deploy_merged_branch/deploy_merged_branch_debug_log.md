# deploy_merged_branch Railspike debug log

## 2026-11-27 UTC — one-shot closeout

### Necessary/useful instructions

- Host `AGENTS.md`, reported failures: "replay that condition or explicitly report it unverified" led to reproducing the sideways scroll at 375px before changing CSS (604–781px page widths) and to the merged-hotfix test mirroring #440. Retain.
- Host `CONTRIBUTING.md`, UI checks in affected themes: the first CSS attempt passed on phones but overlapped the nav at 700px; only the wider check caught it. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons, migrations, read-model schema: none touched.

### Uncertain assessments

- A bug report with screenshots was read as one-shot authority, as with earlier deploy reports. Switching a merged branch back to `main` changes a safety refusal; it is limited to branches fully contained in `origin/main`, which the maintainer may want to confirm.

## 2026-11-27 UTC — one-shot scope change closeout (Return to main)

### Necessary/useful instructions

- One-shot "Resumption and scope changes": appending the changed scope and the maintainer's words before executing kept the record honest about two authorizations on one branch. Retain.
- Host `AGENTS.md`, follow-up changes after a PR: checked PR #443 was still open before pushing to its branch. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons, migrations: none touched.

### Uncertain assessments

- Extending the open PR rather than a new cycle: chosen because the button builds on the unmerged AppUpdate change; a stacked PR would also have worked.
