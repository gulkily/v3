# Offline reader tag links debug log

## 2026-11-18T15:24Z — one-shot closeout

**Necessary/useful instructions**
- The test-first habit in `CONTRIBUTING.md`: running the new assertion without the fix showed it already passed, which exposed the wrong diagnosis before anything shipped. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- UI verification; staged gates; unit estimates.

**Uncertain assessments**
- The record was written from a diagnosis that had not been reproduced on the site, only read off test output. The planning commit therefore describes a bug that does not exist; it is corrected in the execution history, not rewritten.
- Whether a test-only fix needs a feedback item at all.
