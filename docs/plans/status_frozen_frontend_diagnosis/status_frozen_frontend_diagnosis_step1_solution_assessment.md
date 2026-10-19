# Status: Frozen Frontend Diagnosis — Step 1 Solution Assessment

> **Feature plan:** [Step 1](./status_frozen_frontend_diagnosis_step1_solution_assessment.md) · [Step 2](./status_frozen_frontend_diagnosis_step2_feature_description.md) · [Step 3](./status_frozen_frontend_diagnosis_step3_development_plan.md) · [Step 4](./status_frozen_frontend_diagnosis_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
If I'm running ./v3 status because the frontend is frozen, this doesn't tell me anything:

(pasted output: read model ready, freshness ready, schema 21/21, stale marker absent, shared lock unlocked, task queue available with queued: 130, running: 0, failed: 0, rebuild task absent, task queue executor not_observed (last completed: none), automatic schema recovery idle, "Next action: No read-model recovery is currently required.")

## Understood Intent
`./v3 status` only judges read-model health, so it reports "nothing required" while the site is unresponsive. The operator wants it to show why the frontend might be frozen and what to do. Visible hints already in the output (130 queued tasks, no executor ever observed) are not flagged.

## Problem Statement
Status answers "is the read model healthy?" but not "is the web app serving, and what is blocking it?".

## Option A: Smarter Next action from existing data only
Keep the current collectors and add rules that turn data already gathered into warnings, e.g. queued > 0 with executor not observed/stale ("tasks are piling up, no worker running"). No new probes.
- Pros: tiny, no new I/O, cannot hang, easy to test
- Cons: cannot see a hung or dead web server, which is the likeliest cause of a frozen frontend

## Option B: Add a live frontend probe
Add a "Frontend" section: find the `./v3 start` server process (PHP dev server, PID, uptime, listen address) and make a short-timeout HTTP request to a cheap page, reporting `responding (N ms)`, `timed out (server busy or hung)`, or `not running`. Fold the result into Next action.
- Pros: directly answers "is it frozen?"; separates server-down, hung, and healthy-but-browser-side
- Cons: needs address discovery (default 127.0.0.1:8000, custom ports, `--listen-all`); a single-threaded dev server may queue the probe behind the stuck request; new process/network code to test

## Option C: Add a queue/lock backlog view
Extend task-queue output with oldest queued task age, task types, who holds the shared lock and for how long, and the executor's last heartbeat. Warn on stale locks or aged backlogs.
- Pros: explains the "130 queued, executor never seen" case; reuses the queue store; lock holder info helps with real contention
- Cons: still cannot detect a dead or hung web server; may need small queue-store query additions

## Recommendation
Combine A + B as one vertical slice: an at-a-glance "Frontend" verdict (running/responding/timed out/not running) plus rules in Next action that also flag a queue backlog with no executor. Defer C's lock-holder and task-age detail to a follow-up unless Step 2 shows it is cheap. Slice: `./v3 status` on a frozen server prints a specific diagnosis and command to run, with tests for each verdict.

## Revised Direction (user follow-up)
Perhaps I'm being a bit too unhelpfully critical, I just want most common failures to be easy to see in some kind of good/bad indicator set. The technical info that's already helpfully there should remain.

- Keep every existing status line unchanged.
- Add a compact good/bad indicator set (e.g. `[OK]` / `[WARN]` / `[FAIL]`) for the most common failures, placed above or beside the existing detail.
- Candidate indicators: frontend server running and responding (Option B), read model ready, no stale marker, shared lock free, task executor observed, queue not backlogged (Option A rules), no failed tasks.
- Option C's lock-holder and task-age detail stays deferred; an indicator may still use cheap existing counts.
- Revised recommendation: A + B delivered as the indicator set, purely additive to current output.

## Enhancement Requests
None.

Please review and reply `Approved Step 1` to proceed to Step 2, or request revisions.
