# Session member gates — Railspike debug log

## 2026-11-15T22:30Z — one-shot closeout

**Necessary/useful instructions**

- One-shot planning commit gate: writing the excluded list before editing kept the operator gates and verified attribution out of the change. Retain.
- Shared implementation standards, "affected shared consumers": checking browser tests that pass the changed gates surfaced a browser test that already failed on `main`. Retain.
- Maintainer rule to fix pre-existing failing tests: produced the one-line isolation fix that removed 2 to 6 parallel failures per run. Retain.
- "One-shot authorization does not grant merge, push": for a security fix in a public repository this is the right default, since a push discloses the bug. Retain; consider saying so explicitly.

**Redundant/unnecessary instructions**

- No supported findings.

**Inapplicable instructions**

- Staged step gates; extended unit estimates (no alternatives or units); feedback trailer (no matching item); migrations, themes and deployment checks.

**Uncertain assessments**

- The instructions do not say how to handle paid or external test scripts that a change affects but that cannot be run. Recorded here as a limitation with a next action.
