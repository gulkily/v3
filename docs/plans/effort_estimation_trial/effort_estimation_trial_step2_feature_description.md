> **Feature plan:** [Step 1](./effort_estimation_trial_step1_solution_assessment.md) · [Step 2](./effort_estimation_trial_step2_feature_description.md) · [Step 3](./effort_estimation_trial_step3_development_plan.md) · Step 4 pending

Debug mode: enabled

# Prospective effort estimation trial

## Problem

The [calibration study](../../../../../railspike-dev/docs/research/effort_estimation/report.md) proposes retrospective reference scores, but v3 needs prospective evidence of whether those scores help scope work comfortably into Railspike cycles.

The reference report and [implementation diff review](../../../../../railspike-dev/docs/research/effort_estimation/diff_review.md) live in the external `/home/wsl/railspike-dev` checkout. If unavailable in a later session, restore access to the recorded sources and verify their ledger hashes before rating; do not silently substitute revised references.

## User stories

- As the maintainer, I want estimates preserved before delivery so that outcomes can challenge the scale.
- As a v3 developer, I want projected scores recorded during ordinary Railspike steps so that the study reflects real use and changes in understanding remain visible across sessions.

## Core requirements

- Use **1.0 = the largest well-scoped, releasable slice we would comfortably approve for one Railspike cycle, including required verification and documentation**. Keep freeform nonnegative decimals; use the study's startup recovery reference provisionally, subject to maintainer review. Scores are effort judgments, not priority, elapsed time or implementation approval.
- Propose six useful, unfinished scopes starting from the [Zenmemes user feedback summary](../zenmemes_meta_feedback_2026-10-18_summary.md), cross-checking `todo.txt`, `docs/plans/backlog/` and current feature records. Usefulness and unfinished scope take priority; two small, two modest and two near-ceiling items are a sampling preference, not a quota. State each outcome, recovery boundary, dependencies and starting revision. Identify overlaps and prior work; preserve an initial scope and estimate before detailed implementation inspection. Replace unsuitable candidates visibly rather than inventing work to fill a size category.
- Maintain `docs/plans/effort_estimation_trial.md` with links and content hashes for the reference report, diff review and feedback summary, candidate dispositions and a compact numerical comparison table. Preserve inherited feedback scores separately from initial scoped estimates and record raters’ prior exposure. Capture checkpoint estimates using the protocol below; append revisions with scope changes and reasons, never overwrite earlier values. Ordinary backlog estimates need no per-item explanations. Label single-rater results honestly; exposed ratings are not blind or independent of inherited scores. Claim independent ratings only when collected independently before discussion.
- Run selected features through their own Railspike cycles and approvals. At completion, record delivered scope, commits/diffs, planned/completed stages, verification results and omissions, corrections versus new requests, splits and the maintainer's judgment of comfortable fit. Do not derive effort from line counts, stage count or commit timestamps. Record reliable active-work measurements only when available.
- Review the scale after six completed outcomes, separating size errors, scope changes and incomplete acceptance. Recommend retaining or revising anchors without claiming measured labor, an absolute capacity maximum or a delivery forecast. Limit conclusions about the ceiling if near-ceiling work is absent. Pending or abandoned work remains visible; six candidate selections are not six completed outcomes.

## Projected score checkpoints

Each score estimates **total effort for the whole scoped cycle from its starting point**, including planning, implementation, verification, documentation and ordinary correction—not remaining work, elapsed time or a measured actual. Use the same frozen reference set across checkpoints.

| Checkpoint | Record |
| --- | --- |
| Intake | Initial scoped estimate before detailed implementation inspection; keep inherited feedback scores separate. |
| Step 1 handoff, when used | Projection for the recommended scoped approach after assessing alternatives. Mark skipped Step 1 as not applicable. |
| Step 2 handoff | Projection for the defined outcome, scope and recovery obligations. |
| Step 3 handoff | Projection informed by the development plan and required verification. |
| Step 4 entry | Projection before implementation; explicitly reaffirm the Step 3 score if unchanged. |
| Step 4 closeout | Separately labeled retrospective scope assessment, verification status and maintainer comfortable-fit judgment; never relabel this as a prospective score or measured effort. |

Record a score snapshot in each applicable step artifact, linked from the ledger, with checkpoint, date, rater, scope version, reference version, exposure and a brief reason for any change (scope change versus new understanding). Record unchanged scores explicitly. When a step is revised, append its new snapshot; retain earlier handoff/approval context. During implementation, append a revised total projection when discoveries or scope changes warrant it, before dependent work where possible. Preserve approval gates for changed scope. Do not fabricate earlier checkpoints for work already underway: mark them missing or skipped and label any later reconstruction retrospective.

## Delivery scope and completion boundary

**Documentation-only trial setup and vendored-workflow integration.** Allowed files: `docs/railspike/RAILSPIKE.md` (canonical experimental estimation contract), `docs/railspike/docs/workflow/step1_solution_assessment.md`, `step2_feature_description.md`, `step3_development_plan_before.md`, `step3_development_plan_do.md`, `step3_development_plan_after.md`, `step4_implementation_before.md`, `step4_implementation_do.md` and `step4_implementation_after.md` in that same workflow directory (checkpoint and resumption hooks), `docs/reference/railspike_workflow.md` (local customization/provenance and update guidance), `docs/plans/effort_estimation_trial_maintainer_checklist.md`, this cycle's `docs/plans/effort_estimation_trial_step*.md`, `effort_estimation_trial_debug_log.md`, the trial ledger named above, and its entry in `docs/plans/README.md`; organization follows Railspike after Step 3 approval. Audience: v3 maintainer and developers. The feedback summary and backlog are read-only inputs. The vendored instructions enable the experiment for explicitly selected study cycles; unrelated work retains its existing behavior. Keep generic estimation mechanics in Railspike and project-specific references, candidates and data in the host ledger. No `AGENTS.md`, application, test, runtime or deployment changes are permitted. Direct vendored edits are explicitly requested for this exercise; document the local divergence without misrepresenting the installed upstream revision. Upstream roll-up, publishing and subtree updates are separate future work.

This setup is complete when the ledger contains the protocol, six proposed scoped candidates with outcomes, recovery boundaries, inherited scores where available and preserved initial scoped estimates, source provenance and instructions for later updates. Setup also requires that the vendored workflow makes checkpoint recording discoverable during normal selected-feature work and resumption. Verify the instructions through a documented walkthrough of a new cycle, a skipped Step 1, a revised step and a resumed cycle with missing checkpoints; synthetic walkthrough values are not study outcomes. The maintainer reviews selection and the provisional anchor before feature implementation. Later outcome collection and the six-outcome assessment are follow-up study work alongside separately approved features, not claims of completion in this setup cycle. Corrections append dated revisions without erasing earlier estimates or scope.

## Risks

- **Stale or overlapping candidates:** misleading estimates or double counting. Validate current planning/status evidence during selection; check code before committing to a feature and preserve any resulting estimate revision.
- **Anchoring, hindsight or changed units:** incomparable or falsely independent scores. Existing feedback scores are already visible; record exposure, separate inherited and new estimates, and freeze source hashes and the reference definition before rating. Preserve scope versions and anchor revisions.
- **Unbalanced sample:** weak evidence about the ceiling. Check the proposed size mix during selection, favor useful work over quotas, and qualify conclusions when near-ceiling outcomes are absent.
- **Confusing missing checks with excessive size:** unsupported conclusions. Define required evidence with each scope before planning; assess completion quality and comfortable fit separately at closeout.

## Shared component inventory

Reuse the user feedback summary, existing backlog records, feature plans, summaries and Git evidence. Extend the vendored Railspike entrypoint and step/phase instructions, reuse each step artifact for its checkpoint snapshots, and use the ledger to link the study evidence. No application UI or API is needed.

## Simple user flow and success criteria

1. Review feedback and current status; define useful candidate outcomes and recovery.
2. Preserve inherited scores and initial scoped estimates before detailed inspection; record projected totals at each applicable step through the vendored workflow guidance.
3. Obtain each feature’s approvals, deliver it and record evidence; assess after six completed outcomes.

Setup succeeds when another v3 session can recover all estimate versions, source provenance and candidate dispositions from the ledger, distinguish pending implementation from completed evidence, and follow the per-step protocol through the normal vendored workflow without assuming a balanced sample or inventing missing history.

## Review status

Step 1 approved explicitly by the user (“Approved Step 1”). Step 2 is reconciled with Option A and pending approval. Candidate selection, ratings and ledger creation remain setup implementation after Steps 2 and 3 approval.

- **Request:** Ground the trial in actual feedback, prioritize useful unfinished scopes over size quotas, and distinguish inherited scores from subsequent judgments. **Disposition:** Incorporated from approved Step 1. **Approval:** Direction approved; this Step 2 revision awaits approval. **Outcome:** Updated candidate sources, sampling policy, provenance and completion criteria; separate feature approvals remain required.

## Approval and cross-cycle clarification

The user explicitly approved Step 2 and accepted the bookkeeping recommendation: include later ledger updates in each selected feature’s approved documentation scope and closeout commit; conduct the final six-outcome assessment as a separate documentation cycle. This setup does not authorize those later implementations or commits. Step 2 is approved; earlier pending-status entries are historical.

- **Request:** Provide a separate checklist containing only the user’s own actions. **Disposition:** Explicitly authorized documentation addition: `docs/plans/effort_estimation_trial_maintainer_checklist.md`. **Approval:** User requested the file directly; existing setup approval remains recorded. **Outcome:** Separate checklist created; agent bookkeeping stays in the development plan and future ledger.

## Revision request — per-step projections

- **Request:** Integrate experimental estimation into normal host Railspike use and record projected scores at each step. **Disposition:** Expanded documentation scope and checkpoint protocol above; retain the approved feedback-led selection strategy. **Approval:** Revision requested, renewed Step 2 approval pending. **Outcome:** Step 2 revised; Step 3 marked stale rather than silently left as current. Implementation waits for reconciled Step 3 approval. Existing approval records above remain historical.

- **Renewed approval:** The user explicitly responded “Approved Step 2” after the per-step projection revision. The expanded documentation scope and checkpoint protocol are approved; Step 3 approval remains required before implementation.

## Revision request — direct vendored changes

- **Request:** Modify vendored Railspike directly and roll up changes later. **Disposition:** Replace host-only checkpoint instructions with a generic contract and hooks in the named vendored files; retain host-specific study data and document local divergence in the project workflow reference. **Approval:** User explicitly requested this direction; renewed Step 2 approval of the revised artifact is pending. **Outcome:** Step 2 scope revised; Step 3 marked stale pending reconciliation. No vendored files changed yet. Earlier host-only scope and approval records are historical.

- **Renewed approval — vendored integration:** The user explicitly responded “Approved Step 2” to the direct-vendored revision. Its named file scope and checkpoint protocol are approved. Step 3 is now reconciled and awaits approval; earlier pending entries are historical.
