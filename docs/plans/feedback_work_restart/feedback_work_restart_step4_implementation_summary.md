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

## Stage 3 - Pilot migration of two register entries

**Original estimate: 0.06** (unchanged)

- Changes:
  - [`PF-20261021-F01`](../../feedback/items/PF-20261021-F01.txt) (reply focus, the entry with the longest history) and [`PF-20261021-B03`](../../feedback/items/PF-20261021-B03.txt) (saved draft starts collapsed).
  - Register sections for both now start with a `Current item:` link; their fields stay as migration history.
  - Spec: file references in `Notes:` are repository-relative paths.
  - Spec and concept examples now use the illustrative ID `PF-20260101-F01`; the earlier example collided with the register's real `PF-20261103-F01`.
- Verification:
  - Side-by-side review against the register, field by field: alias, workflow state, delivery evidence and date, request, report link, inherited estimate, recommended workflow, done-when, next action, implementation and checklist links, source-code pointer, historical triage link. All carried over; the F01 2026-10-23 investigation stays in the register and is linked by anchor.
  - All linked paths and the checklist anchor exist; `./v3 feedback check`: 2 items, 0 problems; index regenerated.
- Notes:
  - Pilot outcome: the format holds; long investigations belong in linked history, not in the item. No change needed before Stage 4 beyond the path convention.
  - Retrospective stage-work assessment: 0.06, as estimated.

## Stage 4 - Migrate the remaining register entries

**Original estimate: 0.08** (unchanged)

- Changes:
  - 15 item files: `PF-20261021-F02` to `F11`, `B01`, `B02`, `B04`, `B05`, and `PF-20261103-F01`. Converted with a scratch script outside the repository, then checked and hand-corrected.
  - Hand corrections: F02 keeps both its inherited 0.03 and the separate source-informed 0.10; F04 keeps its locally delivered scope and approved-cycle link and has no `Done when:` (the register gave none, so none was invented); `PF-20261103-F01` records the 0.80 Step 1 projection as its estimate with provenance and drops the October 21 triage link it never had; F01 regains the related cycle's boundary link.
  - Every item's `Notes:` starts with the register status line at migration (review date, evidence limits) and ends with a link to its register section.
  - Register: superseded banner pointing at the items and index; every entry starts with a `Current item:` link. Its investigations and the related-reports note stay in place.
  - [`production_feedback_management.md`](../../reference/production_feedback_management.md) now starts from the feedback index and items; ID, workflow, evidence, closure and correction rules kept, with the `O` kind and the item-file spellings of states added.
- Verification:
  - Script comparison of register and items: 17 IDs, 17 items, none missing; every zenmemes.com report link in each entry appears in its item; every document link appears, except the new `Current item:` links themselves.
  - `./v3 feedback check`: 17 items, 0 problems, 2 to review. Both are recorded splits: F08, F09 and F10 share one reply; F02, B01 and B02 share another.
  - `./v3 feedback index` regenerated: 17 open, 0 closed.
- Notes:
  - Retrospective stage-work assessment: 0.10. Higher than 0.08 because the three irregular entries and the lost status lines needed a second pass.
