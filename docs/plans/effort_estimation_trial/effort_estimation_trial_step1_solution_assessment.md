> **Feature plan:** [Step 1](./effort_estimation_trial_step1_solution_assessment.md) · [Step 2](./effort_estimation_trial_step2_feature_description.md) · [Step 3](./effort_estimation_trial_step3_development_plan.md) · Step 4 pending

Debug mode: enabled

# Prospective effort estimation trial — solution assessment

## Original Query

Can you please write a Step 1 plan, using the Step 2 plan as point to reverse from

## Understood Intent

Work backward from the existing, unapproved Step 2 draft to assess how v3 should test the retrospective effort references prospectively. The draft is context, not an already-approved solution.

## Problem

The [calibration study](../../../../../railspike-dev/docs/research/effort_estimation/report.md) and [diff review](../../../../../railspike-dev/docs/research/effort_estimation/diff_review.md) propose relative effort judgments, but do not establish whether those references help scope future work comfortably into Railspike cycles.

## Option A — Feedback-led prospective trial

Prepare a documentation-only protocol and ledger proposing six useful unfinished scopes, starting from the [actual user feedback summary](../zenmemes_meta_feedback_2026-10-18_summary.md) and checking existing backlog and feature records for overlap. Define outcomes and required recovery before rating; seek size diversity where useful work supports it, without a quota. Track revisions and outcomes through separately approved feature cycles.

- **Pros:** Grounds selection in expressed user needs; preserves an auditable starting point and a bounded setup deliverable; avoids inventing work to fill size categories.
- **Cons:** Historical requests may already be delivered or remain underspecified, and existing scores can bias ratings. The sample may not test the ceiling; completing six outcomes depends on later priorities. Requires revising Step 2’s fixed size mix.

## Option B — Rolling trial on the next eligible work

Record estimates and outcomes as ordinary features are selected, continuing until six completed outcomes are available rather than selecting a balanced batch upfront.

- **Pros:** Follows maintainer priorities with less initial selection work; avoids choosing work mainly to fill size categories.
- **Cons:** May provide little evidence near the ceiling; shifting references and inconsistent recording can weaken comparison. Requires revising Step 2's selection and setup acceptance requirements.

## Option C — Extend retrospective calibration first

Review more completed cycles and seek independent judgments of their scope before designing a prospective trial.

- **Pros:** Can challenge the provisional anchor and fill gaps among historical reference examples before new estimates are used.
- **Cons:** Retains hindsight bias and cannot test estimates preserved before delivery; delays prospective evidence and requires a different Step 2 scope.

## Recommendation and completion boundary

Choose **Option A**: usefulness and unfinished scope come first; two small, two modest and two near-ceiling items are a sampling preference, not an acceptance quota. Preserve unsuitable, replaced and pending candidates visibly. If the completed sample lacks near-ceiling work, limit conclusions about the 1.0 anchor rather than manufacture a larger feature. Keep **1.0 = the largest well-scoped, releasable slice we would comfortably approve for one Railspike cycle, including required verification and documentation**; startup dependency recovery remains a provisional reference for maintainer review. Freeze source provenance and preserve the feedback summary’s scores as inherited estimates, separately from new scoped judgments. Record prior exposure to those scores; subsequent ratings by an exposed rater are not blind or independent of them. Preserve initial scoped estimates before detailed implementation inspection, then append revisions and distinguish scope changes and incomplete acceptance from size errors. Label single-rater results honestly; claim independent ratings only when collected independently before discussion.

The viable deliverable for this cycle is a documentation-only setup that a later session can resume: protocol, six proposed scoped candidates with outcomes and recovery boundaries, inherited and initial scoped estimates, reference provenance and update instructions. No application changes or selected-feature implementation belong to this cycle. Each candidate must deliver its own useful outcome and recovery through separate approvals. Six completed outcomes and any anchor revision are later study work; neither setup nor retrospective evidence establishes measured labor or a delivery forecast.

## Request and approval status

- **Request:** Add Step 1 by working backward from the Step 2 draft. **Disposition:** Assess alternatives while preserving that draft as context. **Approval:** Step 1 pending; Step 2 remains unapproved. **Outcome:** This assessment; after Step 1 approval, reconcile Step 2 with the selected approach before requesting its approval.

- **Request:** Ground the trial in actual Zenmemes feedback and revise the recommendation accordingly. **Disposition:** Incorporated in Option A; prefer useful unfinished scopes over size quotas and distinguish inherited scores from subsequent estimates. **Approval:** Revision authorized by “Please go ahead”; Step 1 approval remains pending. **Outcome:** Revised assessment; reconcile Step 2’s candidate sources, size mix and estimate provenance after Step 1 approval.

- **Approval update:** The user explicitly responded “Approved Step 1.” Option A is approved; Step 2 has been reconciled for its own review. Earlier pending-status entries above record the revision history.
