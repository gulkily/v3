Cycle: v3/feedback_completion_prompt; Mode: one-shot; Index: ../README.md
Debug mode: enabled (project marker)
Effort study: railspike-effort; R1/P2; ../effort_ledger/feedback_completion_prompt.md
Extended estimates: enabled

**Cycle estimate: 0.05**

Status: prompt saved and verified; commit and PR handoff pending
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

Planning commit: f07a6a7ab, confirmed before creating the saved prompt or editing the agent index.
Execution: Saved docs/agents/feedback_completion_prompt.txt and linked it from docs/agents/README.md. The prompt preserves delegated record updates, deeper history/source verification, main/live ancestry, honest exceptions, canonical check/index commands, a new PR and concise reporting. It explicitly excludes fixes, intake, production writes, merge and deployment, and distinguishes an open PR from live records.
Verification so far: Prompt text and procedure paths checked; all local links in the agents index resolve. git diff --check passes. Feedback items/index, application source/tests, Railspike and todo.txt unchanged. Project smoke is running; full suite will not be run.

Verification complete: Summary: 488 run, 484 passed, 4 failed. Ran ./v3 test (smoke only). Four existing authored-text protocol failures recur: AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure; AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility; SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection; SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes. These also occurred during the preceding reconciliation on unchanged application source. No full suite run and no runtime fixes in this documentation scope.
Final review: saved prompt and agent-index links resolve; agreed authorization and exclusions retained; Key: value/plain-text format preserved; git diff --check passes; all changes within allowed documentation paths. No application, feedback item/index, dependency or todo changes. Runtime/UI/schema/deployment checks inapplicable.
Outcome: maintainer can find the prompt through docs/agents/README.md and invoke it by path or copy its Prompt section. New PR delivery remains to be completed; no reconciliation or scheduled automation was started.
Study closeout: 2026-11-24; v1/R1/P2; intake/entry 0.05 preserved; retrospective cycle scope 0.05. Single exposed Codex judgment; no independent rating, measured labor or maintainer fit/acceptance inferred.
Attribution: Codex; 2026-11-24 UTC.
