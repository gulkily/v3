> **Feature plan:** [Step 1](./agent_reply_length_step1_solution_assessment.md) · [Step 2](./agent_reply_length_step2_feature_description.md) · [Step 3](./agent_reply_length_step3_development_plan.md) · [Step 4](./agent_reply_length_step4_implementation_summary.md)

Debug mode: disabled

# Scripture Response Guidance and Expansion Implementation Summary

## Stage 1 - Initial response guidance

- Changes: Editable scripture guidance asks for direct, complete answers, actual relevant quotations, meaningful nuance, and natural stopping without quotas or templates.
- Verification: Generator suite: 7 passed; prompt routing and technical exhaustion behavior preserved. Qualitative live review follows in Stage 6.
- Notes: Initial answers must stand alone; no essential detail is withheld for expansion.

## Stage 2 - Canonical expansion provenance

- Changes: Added optional validated canonical Agent-Response metadata and derived JSON column; schema version 18, full/incremental replay, deterministic expansion publication IDs, and recovery of canonical writes interrupted before commit/read-model refresh. Metadata is passed through a server-only writer argument, not public input fields.
- Verification: Parser/schema/startup suites: 47 passed, including prior-version startup rebuild before page access. Writer round-trip/rebuild/replay test passed; an initial test cursor held a SQLite read lock and was corrected before the successful rerun. Isolated `./v3 rebuild` and normal page access passed. Diff check passed.
- Notes: Existing records need no migration; only the disposable read model changes. Existing expansion publication is reconciled under the canonical write lock.

## Stage 3 - Expansion request and fulfillment

- Changes: Added an internal expansion task, dedicated editable prompt with both source texts, request/status endpoints, verified scripture eligibility (including trusted historical request provenance), shared request deduplication, and targeted operator recovery. New scripture responses carry canonical provenance. Expansion rechecks source versions, visibility, and requester approval before and after generation and under the write lock; interrupted posting reconciles its stable canonical identity.
- Verification: Task/generator/store/worker and isolated expansion API tests: 43 passed. Covered duplicate claims/requests, canonical replay/rebuild, source edits/hiding, requester revocation during generation, exhaustion and explicit retry, and recursion rejection. Diff check passed.
- Notes: No separate LLM provider, automatic expansion, or response-length quota was introduced. Failed provider calls remain failures; stale source work is skipped without publication.

## Stage 4 - Shared reader controls

- Changes: Thread/post cards offer Expand only to eligible members, poll durable status without automatically retrying generation, open completed content for the requester, and retain accessible collapsed shared results. Expansion text is never injected as HTML. Canonical expansion children are presented once as supplementary content; their direct post route resolves through the validated initial response.
- Verification: Request/publication/rebuild rendering test passed. Chromium checks passed on thread/post pages at 1280px and 375px: keyboard request, polling/progress, requester auto-open, collapse/reopen, shared completed display, safe text, and no viewport overflow. JS syntax/diff checks passed.
- Notes: Native details/summary provides keyboard semantics. Fixed an existing long agent-feedback label overflowing narrow action rows while validating this experience.
