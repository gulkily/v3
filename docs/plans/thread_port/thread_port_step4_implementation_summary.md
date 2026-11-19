> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment.

Extended estimates: enabled. Stage estimates are copied from Step 3, not newly rated.

Cycle estimate: 0.95

Branch: `thread-port`, from `origin/main` `7d9b4b4d`; planning commit `2e97e6ca`.

Step 4 entry (2026-11-19 UTC): Step 3 projection **0.95** reaffirmed after the planning commit, before implementation. Claude, same exposed rater.

## Stage 1 - Ported headers and read-model columns

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - New `PortedSource` reads the four `Ported-*` headers, all or none, with Hacker News item addresses only. `PostRecord` gains `ported`.
  - `posts` gains `ported_from`, `ported_author`, `ported_created_at` and `ported_by`, filled by the full rebuild and both incremental insert paths. A retraction clears them. Schema 55 → 56; `read_model_schema_v1.md` updated.
  - Concept entry `docs/concepts/ported_thread.md` created.
- Verification:
  - `./v3 test ThreadPortRecordTest`: 3 passed. These cover parsing, partial and wrong-site rejection, and a schema-55 database rebuilt at startup before the columns are read.
  - `./v3 test --smoke EventWhenRecordTest TagUnlocksTest`: 498 passed, 0 failed.
- Notes: `tests/run.php` lists test files by hand; the new file is added there.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
