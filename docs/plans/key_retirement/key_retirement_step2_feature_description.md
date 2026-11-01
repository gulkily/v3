> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement)
Extended estimates: enabled
Status: Step 2 v2/A approved on 2026-11-01; Step 3 proposed; implementation unapproved

**Cycle estimate: 1.00**

## Problem and user stories

Obsolete account keys retain access, while deleting their verification material would damage history. Members and operators need deliberate retirement that removes future privileges and preserves existing evidence and retained message access.

- As a member, I want to retire an old key using another active account key so that the old device loses account access.
- As a root-approved operator, I want to approve a replacement and retire a lost key so that the member can regain account access safely.
- As a reader, I want historical signatures, approvals and retained encrypted transfers to remain usable after retirement so that device changes do not rewrite history.

## Core requirements

- Authority: An active approved key may retire a different active approved key of the same account; an active root-approved operator may retire another account's key. Bind actor, target and reviewed authority in a signed plain-text record. Same-account authority requires accepted membership, not an asserted name. Ordinary members cannot retire other accounts' keys under either value of `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`. Recheck current authority at acceptance, including operator status and concurrent changes; a retired operator's retained seed is not active authority.
- Access: Accepted retirement prevents new authentication and protected actions from existing/forwarded sessions, direct signed API requests and queued message work. Exclude retired keys from new message recipients and recovery destinations. Keep public reading and basic participation available; signature validity alone must not confer current member privileges. Never silently rewrite a rejected action or discard a draft.
- Preservation: Keep public keys, exact signed bytes, historical approvals and previously valid message/transfer evidence. Retiring an approver must not unapprove their past beneficiaries. Distinguish historical signer/donor verification from permission to create new transfers; acceptance history must reject backdated post-retirement authority. Existing readable messages remain readable on active keys after reload and restore. No promise to recall delivered plaintext or recover messages without retained encrypted access.
- Protection and recovery: Neither path may remove the final active approved key, including concurrent reciprocal retirement. Approve and verify a usable replacement first when the sole key is lost. Repeated submissions are idempotent; uncertain results can be checked and safely retried. Proposed reactivation policy: retirement is final in this slice; old approvals, imports, re-registration and new approval attempts cannot reactivate the fingerprint. Use a replacement key; reversal and compromised-key revocation are separate work.
- Presentation: Identify the exact key and account, show material access consequences before signing, and show retained retirement status/history afterward. Keep retirement distinct from browser-local Remove. Reuse shared controls, minimal copy and static viewer-neutral HTML, with client decisions before first paint. Pending/failure states retain a clear recovery action.

## Delivery scope and completion boundary

Work type: Application change, after planning approval. Deliver the normal Account → account keys → target key retirement journey and the operator Users/Access → account → target key journey, with signed API support, eligibility enforcement, replay/rebuild/export/restore and proportionate UI verification. Reuse existing dependencies; dependency delta zero. No key cap, bulk cleanup, automatic expiry, account disabling, new flag, private-key custody or new navigation destination.

Include this cycle's plan/debug/study records, relevant concept entries, canonical-record/API/schema documentation where affected, and UI verification records. Completion requires both journeys to work from ordinary navigation, retired access to fail, retained history to survive, and interruptions to permit status inspection/retry. Merge, deployment and production acceptance are not implied.

## Shared component inventory

| Existing surface | Treatment |
| --- | --- |
| Account (`account_key.php`, account state/menu), its local Remove/Undo | Extend entry to shared account-key management; preserve local removal meaning. |
| Standard/Forte user and profile pages, paned user/detail/profile-summary views | Extend existing shared key/status presentation; link to the same retirement flow. |
| Users, pending approvals, root approval, invitations; User Access account/profile pages and approval history | Preserve approval history, distinguish retired from pending, prevent accidental reapproval; reuse access/key detail components. |
| Profile API, identity bootstrap/linking, approval prepare/review/submit and signed-post entry | Extend current eligibility/status contracts; add a dedicated signed retirement action because approval grants access rather than withdrawing it. |
| Authentication/status/resume, toolbar identity, private Messages/conversation/access and background recovery | Reuse current identity and recovery components; reflect real retirement changes and enforce active versus historical eligibility consistently. |
| Read-model replay/incremental updates, activity/access evidence and backup/export/restore | Carry the retained signed retirement and historical authority through existing record/evidence machinery; preserve prior grants and signatures. |

## Flow and success criteria

1. Open the account's keys from Account or Users/Access and select the exact old key. If it is the final active key, approve a replacement and verify access first.
2. Review the target and consequence, sign with an eligible member/operator key, and submit. Stale authority requires fresh review; an uncertain response offers status inspection and retry.
3. See the retired status and signed evidence; confirm the replacement works and the retired key cannot authenticate or exercise account privileges.

Acceptance: Both authorized paths succeed with either approval-flag value; unrelated members and retired actors fail. Final-key races cannot leave zero active keys. Existing/forwarded sessions and direct API calls cannot bypass retirement. Rebuild and export/restore preserve retirement, prior approvals and old-message verification/recovery, including retired original signers and donors. Current and supported legacy message flows retain bounded work. Verify phone/desktop, relevant themes, first paint, long fingerprints, failure/retry and keyboard access; no-JavaScript reading/basic posting stays available. Report production and physical-device omissions separately.

## Risks and early validation

- Historical authority versus present eligibility: trace original signers, donor receipts and approval replay before committing the development plan; require retained acceptance evidence rather than trusting a supplied timestamp. If the existing evidence cannot support this boundary, revise Step 2 before implementation.
- Authorization races: enumerate session, operator, signed-action and acceptance paths during Step 3 planning; require acceptance-time checks and concurrent last-key verification.
- Lockout or misleading recovery: validate the replacement-first journey and final retirement warning before implementation; future cap handling remains a separate dependency. Replacement approval does not itself restore old-message plaintext.

## Concepts and approval checkpoint

Concepts touched: Propose key retirement and active-key eligibility inventory entries. Reuse [signed approval review/acceptance](../../concepts/approval_acceptance.md), preserving historical grants; change [history recovery/sender assistance](../../concepts/private_message_history.md) eligibility and invalidation while retaining historical verification. Extend [profile delivery](../../concepts/profile_delivery.md) status presentation within its static delivery constraints. Update only these touched concepts during implementation.

Authorization: User said “approved step 1” for expanded Option A. This authorizes Step 2 preparation, not implementation. Proposed final-retirement policy above is part of Step 2 review.
Study checkpoint: 2026-11-01 UTC; railspike-effort / v3/key_retirement; scope v2/A clarified; original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4, reviewed main f82bdc3e9; R1/P2; Codex single exposed rater. Whole-cycle projection 1.00 reaffirmed; original v1/A 0.95 and Option B 0.65 retained in Step 1. Frozen R1 hashes verified this session. No measured labor, independent rating or accepted-count increment.
Verification: Current surface/source inspection and document whitespace/link checks only; no runtime/UI/deployment tests. Planning remains uncommitted. Await Approved Step 2 before the development plan.

*— Codex · 2026-11-01 UTC*


Approval record: 2026-11-01 UTC — user said “approved step 2.” Approved v2/A includes permanent retirement with replacement-key recovery. Continue to Step 3 planning; prior pending-approval wording is historical.

*— Codex · 2026-11-01 UTC*
