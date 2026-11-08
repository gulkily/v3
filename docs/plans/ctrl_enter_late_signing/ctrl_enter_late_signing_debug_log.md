# Ctrl+Enter after late signing load — Railspike debug log

## 2026-11-08T17:20Z — one-shot closeout

**Necessary/useful instructions**

- `docs/agents/thread_to_pr.md` "Replay the reported condition when it is a bug": every local and headless replay passed, so the cause was found only by asking the reporter (the maintainer, present in the session) to run diagnostics in his own browser. Retain.
- `AGENTS.md` reported-failure rule ("a pass elsewhere does not establish recovery"): kept the session from shipping a guess after the first green replays. Retain.

**Redundant/unnecessary instructions**

- None.

**Inapplicable instructions**

- Staged step gates and stage commits (one-shot mode); extended unit estimates (one approach); UI copy and nav icon checks (no UI change).

**Uncertain assessments**

- Whether the bug report's thread counts as "the requester just wants it done" or as the maintainer: the reporter is the maintainer, so questions went to him in the session instead of to the thread.
