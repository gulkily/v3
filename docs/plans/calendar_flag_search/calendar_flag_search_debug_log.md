# Calendar flag search — one-shot debug log

## 2026-11-20T19:31:07Z — one-shot closeout

- Debug mode enabled by the host marker. Scope v1 unchanged; R1/P2 standing enrollment.
- Local plan commit 1d34c2e preceded product and browser test edits. Execution-entry reaffirmation was recorded after the plan commit, before the product edit.
- Chromium could not start because its local socket was denied. A disposable Node VM harness exercised the real browser-filter code and PHP-rendered flag search attributes, with fail-before/pass-after evidence; this is not visual browser acceptance.
- The smoke runner hit the same 65 local failures on clean main and the proposed fix, with 423 passes each. The two focused suites passed all 30 checks. The full suite was not run.
