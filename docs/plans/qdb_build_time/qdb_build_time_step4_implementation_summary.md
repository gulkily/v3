> **Feature plan:** [Step 1](./qdb_build_time_step1_solution_assessment.md) · [Step 2](./qdb_build_time_step2_feature_description.md) · [Step 3](./qdb_build_time_step3_development_plan.md) · Step 4

State: Approved Step 3 received; implementation in progress on feature/qdb-build-time.
Planning commit: f526da98. Frozen baseline: 4abda3db, /tmp/qdb-build-time-baseline.
Inherited state: Prior pagination cycle complete; preserve unrelated audit drafts, ledger edits and prior-cycle host report. No merge, push or production changes in this cycle yet.
Study: railspike-effort, R1/P2, v3/qdb_build_time; original v1/A 0.50 retained, selected v2/C 1.25. Single exposed Codex rater; reliable measured labor unavailable.

**Entry cycle estimate: 1.25**

## Stage 1 — Reproducible baselines and parity fixtures

**Original stage-work estimate: 0.15**
**Retrospective stage-work assessment: 0.18**

- Changes: Fixed corpus generator and separate-operation harness; frozen full row outputs beside baseline checkout. Added production rendering batch timings.
- Verification: Frozen baseline activity suite 8/8, normal CLI static build 1/1; instrumented CLI static test 1/1. Edge and 10000-event full row streams repeat exactly; empty/missing Git produce empty streams. Valid 5215-profile baseline: 115.4633 seconds, 10 MiB PHP peak, 5215 files. PHP lint and diff whitespace checks pass. Failed exploratory samples and fixes retained in findings. Stage 7 owns repeated matched acceptance.
- Notes: Originals for subsequent stages: S2 0.16, S3 0.15, S4 0.20, S5 0.23, S6 0.16, S7 0.15, S8 0.10. Scope unchanged from approved Step 3; no revisions.

*— Codex · 2026-10-30 UTC*
