# Open beside offline debug log

## 2026-11-18T15:16Z — one-shot closeout

**Necessary/useful instructions**
- `AGENTS.md` "inspect existing implementations" and "reproduce": a scratch run offline showed three faults in the pane (stale bar title, a second offline bar, the browser's error page) that reading the code had not shown. Retain.
- The maintainer's rule that a test failing on the base commit is fixed as part of the work: three `OfflineNavigationWorkerTest` cases, confirmed on the base in a clean worktree. Retain.
- One-shot "Approach and risks": the reply-from-the-pane risk was checked first and did not appear. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Staged step gates; unit estimates; migrations and deployment checks.

**Uncertain assessments**
- Authorization: the maintainer raised the gap and confirmed the symptom but did not say "go ahead". Recorded as such in the record.
- The record wrongly said PR #335 was not in the base; corrected with a dated note.
- Firefox offline could not be emulated here, so "Known environment" evidence for Firefox is missing.
