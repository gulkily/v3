> **Feature plan:** [Step 1](./feedback_work_restart_step1_solution_assessment.md) · [Step 2](./feedback_work_restart_step2_feature_description.md) · [Step 3](./feedback_work_restart_step3_development_plan.md) · [Step 4](./feedback_work_restart_step4_implementation_summary.md)

Effort study: `railspike-effort` / `v3/feedback_work_restart`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Branch: `feedback_work_restart`, based on `remove-work-tracking` (`81837cf`). Planning commit `6e1909f`.

**Cycle estimate: 0.60**

## Step 4 entry projection

2026-11-04; Claude, single exposed rater; R1 not re-verified in this session. **0.60**, reaffirming Step 3. Stage identities and original stage-work estimates are copied from [Step 3](./feedback_work_restart_step3_development_plan.md); copying is not a new rating.

## Stage 1 - Item format and check command

**Original estimate: 0.12** (unchanged)

- Changes:
  - Spec [`feedback_item_record_v1.md`](../../specs/feedback_item_record_v1.md): headers `ID`, `Title`, `Kind`, `Workflow`, `Created`, optional `Evidence`, `Estimate`, `Aliases`, `Duplicate-Of`, `Superseded-By`; body sections `Request:`, `Sources:`, `Done when:`, `Notes:`. Sources are body lines because the `Key: value` parser rejects repeated headers (Step 3 risk, resolved as planned).
  - `src/ForumRewrite/Feedback/FeedbackItem.php`, `FeedbackItemCheck.php` (reuses `GenericTextRecordParser`); `scripts/feedback.php check [--dir=]`; `./v3 feedback` dispatch and usage line.
  - Concept entry [`feedback_items.md`](../../concepts/feedback_items.md).
- Verification:
  - `./v3 test --smoke FeedbackItemCheckTest`: 429 run, 429 passed (7 new tests: valid item, malformed and unknown headers, ID vs filename and kind, invalid values and missing sections, missing duplicate target, shared source, command exit code). Full suite not run.
  - `./v3 feedback check` on the empty folder: exit 0.
- Notes:
  - Deviation from Step 2 wording: a post shared by two items is listed as `Review:` and does not fail the check. The October 30 summary splits combined posts into separate outcomes on purpose, so a shared source is often correct. Malformed files, bad values and missing duplicate targets still fail.
  - Duplicate IDs cannot occur among loadable files because the filename must equal the ID; the cross-file duplicate check is kept as a guard.
  - Retrospective stage-work assessment: 0.12, as estimated; the shared-source decision was the only surprise.

## Stage 2 - Generated index

**Original estimate: 0.06** (unchanged)

- Changes:
  - `src/ForumRewrite/Feedback/FeedbackIndex.php`: open items by kind (features, bugs, other), ordered in-progress, blocked, planned, ready, untriaged, then ID; closed items in one table with their outcome and duplicate or superseding target; table pipes escaped; atomic write via a temporary file and rename.
  - `./v3 feedback index [--dir=]` writes `README.md` beside the items folder, refuses to write while `check` reports problems, and reports `Unchanged` when the output is identical.
  - Generated [`docs/feedback/README.md`](../../feedback/README.md) (empty until Stage 3); concept entry updated.
- Verification:
  - `./v3 test --smoke FeedbackItemCheckTest`: 430 run, 430 passed (new index test: refusal on problems, grouping and order, escaping, closed outcome links, unchanged rerun). Full suite not run.
- Notes:
  - Retrospective stage-work assessment: 0.06, as estimated.
