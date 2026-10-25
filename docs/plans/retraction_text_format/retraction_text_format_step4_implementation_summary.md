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

## Stage 2 - Atomic retained evidence

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: acceptance reads all three bound text files; deletion installs request/signature before the receipt and only then deletes the post. Atomic file replacement avoids partial receipts. Scoped commit/restore preserves unrelated staging; complete receipts support interrupted retry.
- Verification: receipt/transaction suites **6/6**. Injected exceptions and actual subprocess exits at all six write/delete/stage boundaries; rejected Git commit, five-path successful commit, missing signature, repeat/no-extra-commit and unrelated index checks pass.
- Notes: interruption test exposed staged-deletion paths disappearing from the index; include HEAD paths in the commit and avoid re-adding already staged absent paths. Fixed and rerun. No new journal, dependencies or history migration. Stage 1 commit `d4c5cb1c`; S2/v1 unchanged. Existing legacy/application fixtures are reconciled in dependent stages; this intermediate commit is not releasable.

*— Codex · 2026-10-25 UTC*

## Stage 3 - Signing and retry integration

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: author/operator finalize retains detached request/signature and exposes `record_path`, `signature_path`, `acceptance_path`. Removed old request conversion from prepare/finalize; incomplete unaccepted files cannot bypass fresh authorization/signature checks. Retry marks derived state stale and refreshes without the original post. Attribution now stays a plain internal value instead of a JSON trailer round trip.
- Verification: six focused signed API tests passed: deletion/path inspection plus actual rebuild, author invalid-signature/unauthorized actions, seven account-attribution scenarios, enqueue/artifact/refresh failures and no-new-commit retry. Operator privilege-recheck scenario passes its signing/revocation/rebuild checks but its later import assertion fails on the new receipt family; Stage 4 owns that known integration dependency.
- Notes: S3/v1 unchanged; Stage 2 commit `95fc49bb`. No new UI or dependencies. Source/import contract not yet releasable.

*— Codex · 2026-10-25 UTC*
