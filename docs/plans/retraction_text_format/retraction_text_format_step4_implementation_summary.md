> **Feature plan:** [Step 1](retraction_text_format_step1_solution_assessment.md) · [Step 2](retraction_text_format_step2_feature_description.md) · [Step 3](retraction_text_format_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/retraction_text_format`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_text_format). Extended estimates: enabled.

**Cycle estimate: 0.60**

## Entry — 2026-10-25 UTC

Step 3 explicitly approved. Branch `feature/retraction-text-format`; planning commit `75ec624b`. Scope v3/Option B, original baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2; exact frozen hashes previously verified and rechecked at entry. Codex single exposed rater; **0.60 reaffirmed**, not measured labor. Original S1–S6/v1 stage estimates: 0.15, 0.20, 0.15, 0.20, 0.10, 0.15; scopes/drivers inherited from Step 3, Stage 6 owns shared acceptance. No old-retraction compatibility, dependencies, deployment or other-family conversion. Planning diff check reported Markdown hard-break trailing spaces; normalize these in the next stage without changing content. Policy/audit/ledger remain outside the planning commit.

*— Codex · 2026-10-25 UTC*

## Stage 1 - Text contract

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: strict RetractionReceipt codec/path helpers and canonical specification; current writer still unchanged. Exact request/signature hashes, structure and unknown/duplicate-header validation. Normalized planning Markdown whitespace.
- Verification: `php tests/run.php RetractionReceiptTest` **3/3**, including actual GPG signature verification after round trip, substitution/malformed cases and no prose retention. Diff checks passed.
- Notes: S1/v1 scope unchanged; local receipt hashes do not confer archive authority. Filesystem/missing-file cases belong to Stage 2. Zero dependencies.

*— Codex · 2026-10-25 UTC*
