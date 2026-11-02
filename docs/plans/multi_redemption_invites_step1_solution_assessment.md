> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · Step 2 (not started) · Step 3 (not started) · Step 4 (not authorized)
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [P2 ledger](effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Cycle estimate: 0.85

## Original Query

“Make the expiry 30 days by default, no selector. Write a plan for multiple redemption invites.”

## Problem

A shared link can currently approve only one identity; supporting a group requires consistent issuance, redemption, revocation and approval replay rules.

## Option A — Fixed redemption limit (recommended)
**Estimate: 0.85**

Let an issuer create one link for up to a chosen number of distinct recipient keys. Keep single-use as the default and use the fixed 30-day browser expiry, with no expiry selector.

- Benefit: a group can share one link with a clear stopping point; existing invites stay single-use.
- Cost: a small use-count control and atomic accounting for concurrent claims. Counts mean keys, not proven people.
- Driver/uncertainty: preserve signed grants, retry recovery and rebuild/import consistency when the final slot is contested.

## Option B — Reusable until expiry or revocation
**Estimate: 0.70**

Offer a reusable invite mode without a redemption count; each distinct eligible key can redeem until the link expires or its issuer revokes it.

- Benefit: simpler sharing and no capacity accounting; useful for an open group.
- Cost: a forwarded link can admit any number of keys during its lifetime; the issuer must revoke it to stop admission early.
- Driver/uncertainty: multi-recipient replay and revocation still change, and an unbounded grant needs clear issuer-facing meaning.

## Recommendation and release boundary

Choose A as one releasable vertical slice: issue a capped link, admit several keys, recover interrupted claims, and revoke remaining uses through the existing Invite page. The cap is a proposal, not an inferred user requirement; Option B remains available for review.

Acceptance boundaries: count each successfully accepted recipient key once; retries and already-approved entry consume no additional use; concurrent claims cannot exceed the cap; revocation stops future admissions even after partial use without removing earlier approvals. Expiry must not erase accepted grants on rebuild. Preserve existing single-use records, signatures, same-recipient recovery and targeted/untargeted entry. Verify malformed limits, expiry, exhausted/revoked links, concurrent final-slot claims, lost responses, full/incremental replay and export/import, plus phone/desktop issuance and recovery. Define old-reader behavior before introducing reusable records; older readers currently assume one redemption. Retain readable `Key: value` records and existing approval authority; no dependencies or deployment proposed.

Concepts touched: [Invitation approval replay](../concepts/approval_acceptance.md#invitation-approval-replay) changes for either option; [Approval acceptance](../concepts/approval_acceptance.md#approval-acceptance) and [Signed approval review](../concepts/approval_acceptance.md#signed-approval-review) retain their authority semantics. Invitation lifecycle inventory entry is proposed, to be added only during authorized implementation.

Enhancement requests: the 30-day default/no-selector change is separately authorized and implemented directly; existing signed expiry dates remain unchanged. Multi-redemption implementation and Steps 2–4 are not authorized.

## Study snapshots


Intake: 2026-10-30 UTC; scope v1; baseline 2e17c971308a710a540c905e738048c4f51e53ca plus uncommitted invite-copy cleanup; proposed outcome is bounded multi-recipient invites with compatible replay, atomic limits, retry recovery and browser verification. Projected whole-cycle effort: 0.85. R1 exact three source hashes verified; standing P2 enrollment; Codex single exposed rater. Earlier expiry/redemption source inspection disclosed; not an independent or blind rating. The separately authorized 30-day default change is outside this cycle. No measured effort, implementation approval or accepted outcome.

Step 1 handoff: 2026-10-30 UTC; scope v1 / R1 / P2; 0.85 projected total reaffirmed for A. Option A/v1 0.85 and B/v1 0.70 are alternative whole-cycle estimates, not additive; include inspection, planning, implementation, verification, documentation and ordinary correction. Main inspected boundaries: `InvitationRecordParser`, `InvitationLedger`, `InvitationApprovalReplay`, `LocalWriteService` and browser issuance/redemption. No measured work or maintainer fit judgment. Await Approved Step 1; planning and ledger remain uncommitted.

*— Codex · 2026-10-30 UTC*

Publication authorization: 2026-10-30 UTC — user requested “please commit and push what we have so far.” This authorizes publishing the current planning draft and study records, not Step 1 approval or multi-redemption implementation.

*— Codex · 2026-10-30 UTC*
