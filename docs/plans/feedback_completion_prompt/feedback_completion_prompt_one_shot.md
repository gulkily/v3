Cycle: v3/feedback_completion_prompt; Mode: one-shot; Index: ../README.md
Debug mode: enabled (project marker)
Effort study: railspike-effort; R1/P2; ../effort_ledger/feedback_completion_prompt.md
Extended estimates: enabled

**Cycle estimate: 0.05**

Status: planning; implementation commit and PR pending
Original request: "please save it in the repo somewhere we can find it"
Authorization: Save the completion-reconciliation prompt proposed in this conversation and make it discoverable. Existing repository/PR instructions apply. No new reconciliation run or scheduler is requested.
Scope: Documentation only: docs/agents/feedback_completion_prompt.txt, docs/agents/README.md, this cycle folder, docs/plans/README.md entry and cycle ledger. Do not change feedback items, generated index, application code, dependencies, Railspike or todo.txt.
Reuse: Link the new plain-text prompt from the existing agent-procedure index. Refer to feedback_triage.md for the canonical reconciliation and record-writing procedure rather than duplicate it. Feedback items concept remains accurate.
Starting revision: 6b66069d2977464a3938510add65519df512b28e, fresh origin/main; branch docs/feedback-completion-prompt.
Completion/recovery: A maintainer can find and copy the saved prompt from docs/agents/README.md; invoking it grants bounded status-reconciliation authority while preserving evidence and production boundaries. Correct the saved text through normal review if needed. Commit, push and open a new PR.
Risk: Future agents might treat a request to reconcile as authority to build fixes or deploy. Preserve explicit exclusions, truthful evidence, concise reporting and the requirement to check merge/deployment ancestry.
Verification: Read the saved prompt against the agreed wording and existing procedure; check local links, Key: value format, allowed file scope and git diff --check. Run project smoke; full suite not run. Runtime/UI/migration checks inapplicable to saving instructions.
Concepts touched: Feedback items used unchanged; no concept inventory additions.
Study intake/entry: 2026-11-24, 0.05 after relevant procedure inspection; pristine intake missing. Same scope v1/R1/P2; exposed Codex rater, bundled R1 available, hashes not reverified. No measured labor or acceptance inferred.
Attribution: Codex; 2026-11-24 UTC.
