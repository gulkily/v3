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
