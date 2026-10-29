> **Feature plan:** [Step 1](approval_acceptance_text_step1_solution_assessment.md) · [Step 2](approval_acceptance_text_step2_feature_description.md) · [Step 3](approval_acceptance_text_step3_development_plan.md) · [Step 4](approval_acceptance_text_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/approval_acceptance_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Cycle estimate: 0.70

## Original Query

Please go ahead with F03.

## Understood Intent

Start the next canonical-format conversion from the [audit](../human_readable_formats_checklist.md): readable approval acceptance receipts, preserving approval authority and signed review bindings. This authorizes assessment; staged approvals remain required.

## Problem and concepts

JSON acceptance receipts expose nested approval-policy evidence, while existing signed grants bind the exact context serialization.

Concepts touched: Account-key approval acceptance (changed representation); signed approval review and invitation replay (used unchanged). Inventory entries are proposed for implementation; no host inventory exists yet. Evidence: [receipt contract](../../specs/approval_acceptance_record_v1.md), [codec](../../../src/ForumRewrite/Security/ApprovalAcceptance.php), [policy](../../../src/ForumRewrite/Security/ApprovalPolicy.php).

## Option A — Preserve existing approval evidence (recommended)

**Estimate: 0.70**

Write named text fields for new receipts and provide validated conversion of existing receipts, retaining a legacy reader for old repositories/backups. Keep grant/signature bytes and the existing review-digest algorithm unchanged; JSON used privately to reproduce the digest remains machine serialization.

- Isolate legacy file decoding behind one removable compatibility boundary; current text receipts and consumers must work without it. Provide a readable readiness check for remaining legacy receipts and a documented removal procedure covering readers, path recognition and tests. Check old-backup obligations separately; an empty live inventory does not prove old archives are dispensable.
- Preserve historical signed-digest verification independently of the legacy file reader: converted text receipts can still require the old digest algorithm. Removing the reader must not invalidate those grants.
- Preserves existing account approvals, acceptance-time facts and restore capability without assuming records are disposable.
- Costs compatibility, conversion/retry and conflicting-receipt checks. Historical context ordering and types must reproduce signed digests exactly; unsupported evidence must fail explicitly, never be guessed or silently downgraded to missing evidence.

## Option B — Fresh text format only

**Estimate: 0.45**

Support only future text receipts, with explicitly defined text context hashing for newly prepared grants; abandon previous receipt evidence and recreate dependent approvals where needed.

- Smaller codec and recovery surface; no legacy conversion or digest compatibility layer.
- Requires an explicit F03 decision that affected grants/identities can be discarded or re-established. Dropping receipts alone loses review evidence and can affect invitation replay. No such permission currently exists; F01/F02 exceptions do not apply. Old-backup handling needs a separate explicit decision.

## Recommendation and boundary

Choose A unless the maintainer explicitly confirms F03 evidence is disposable. Deliver one slice covering direct approvals and invitation redemption through receipt source/history, full and incremental rebuild, trusted restore, remote-import exclusion, and pre-/post-commit recovery. Preserve local acceptance versus member-signature authority and legacy unknowns. No policy redesign, unrelated formats, dependency additions, production conversion, deletion or deployment. Proposed documentation scope includes this cycle, its study index, receipt spec, relevant runbook/tests and touched concept entries. Conversion mechanics and duplicate handling must be specified before implementation.

## Study checkpoint and approval

Cycle: v3/approval_acceptance_text; scope v1/A; baseline d26a90f9b9bfa8eb67e72804a7c24707947ab7c0; reference R1; protocol P2, standing enrollment.
Checkpoint: Step 1 handoff, 2026-10-29 UTC; Codex, single exposed rater; 0.65 projected total including inspection, planning, implementation, verification and documentation. Pristine intake was not captured before source inspection and remains missing. Exposure includes audit/prior cycle scores, receipt/policy/writer/import source and existing tests. All three frozen R1 hashes verified. Alternatives are complete-cycle judgments, not additive, hours or measured effort; compatibility/recovery drives A's extra work. No accepted outcome or implementation; await Approved Step 1. Planning stays uncommitted.

*— Codex · 2026-10-29 UTC*

## Revision 1 — Removable legacy support

Request: “We should include an easy way to later remove the legacy reader.”
Disposition: Incorporated into Option A as scope v2/A: isolated compatibility boundary, readable readiness check, documented removal procedure and verification with legacy reading disabled. Actual retirement remains later work requiring an explicit backup/restore decision; no automatic sunset or deletion. This revision is authorized planning, not Step 1 approval.
Study checkpoint: 2026-10-29 UTC, Step 1 revision 1; whole-cycle 0.70 replaces the preserved v1 0.65 projection because the requested readiness/removal path adds scope. Option B remains 0.45. Same baseline, R1/P2, single exposed Codex rater and missing pristine intake; frozen hashes reverified. No implementation or accepted outcome; await Approved Step 1.

*— Revised by Codex · 2026-10-29 UTC*

Approval update: Approved Step 1 received for revised Option A; requirements are recorded in [Step 2](approval_acceptance_text_step2_feature_description.md). Earlier awaiting-approval snapshots remain historical.
