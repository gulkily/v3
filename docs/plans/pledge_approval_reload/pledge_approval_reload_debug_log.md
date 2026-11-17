# Pledge approval reload debug log

## 2026-11-17T22:00Z — One-shot closeout

**Necessary/useful instructions**
- `AGENTS.md` "post-paint updates only for real state changes": an approval is one, and a reload keeps the page final on first paint instead of patching rows in place. Retain.
- The maintainer's rule on pre-existing failures: none found in the tests run.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- The browser test mocks the server; the end-to-end path (approve, server clears the page, reload shows the count) rests on #317's PHP test plus this mocked reload.
