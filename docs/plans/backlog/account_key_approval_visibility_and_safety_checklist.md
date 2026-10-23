# Account key approval visibility and safety checklist

Status: Approved priorities 1–3 are implemented and locally release-verified. All six stages of the combined cycle are complete, including operator documentation and deployment/recovery rehearsal. Operator-controlled ordinary-key revocation and replacement recovery are also implemented and locally verified in the [revocation cycle](../account_key_revocation/account_key_revocation_step4_implementation_summary.md). Community-visible events, notifications, device pairing, owner self-service and root-key recovery remain deferred follow-up work. Checked items describe completed work or decisions, not production deployment.

Reduce easy account impersonation while preserving open registration, member sponsorship, and convenient device setup. Prioritize the cross-account approval restriction, clear approval wording, and visible account-key history. Notifications and device pairing can follow as separately scoped features.

The [combined Step 1 assessment](../account_key_approval_safety/account_key_approval_safety_step1_solution_assessment.md) and [Step 2 feature description](../account_key_approval_safety/account_key_approval_safety_step2_feature_description.md) are approved for Option B, grouping priorities 1–3 with a default-enabled flag. The [Step 3 development plan](../account_key_approval_safety/account_key_approval_safety_step3_development_plan.md) is approved; Stage 1 was implemented in commit `c7ba748b` on `feature/account-key-approval-safety`. The [Step 4 implementation summary](../account_key_approval_safety/account_key_approval_safety_step4_implementation_summary.md) records all six completed stages and their verification limits. Stage 6 was committed as `e48ac882b`; the cycle was subsequently merged in `382b2d652`. The [approval policy FDP checklist](account_key_approval_policy_fdp_checklist.md) supplies the narrower flag requirements within that scope. See also the [findings and recommendations](account_key_approval_policy_findings_and_recommendations.md). Select and scope work through the [Railspike workflow](../../railspike/RAILSPIKE.md).

## Priority 1 Prevent unintended access and clarify approvals

- [x] Implement the default-enabled restriction flag, including invitation enforcement and acceptance-time checks. Local release handoff is complete; instance-specific production rollout remains separately authorized work.
- [x] Preserve member sponsorship of new usernames, approval of additional keys for the approver's own username, and root-approved operator authority when the restriction is active.
- [x] Use action-specific approval wording instead of a generic approval button.

| Situation | Suggested wording |
| --- | --- |
| First approved key for a username | Approve new member Bob |
| Additional key for the approver's username | Add this key to my account |
| Additional key for a different existing username | Grant this key access as Bob |

- [x] For an additional key to another existing account, show the current approved-key count and the consequence before signing: “Bob already has 2 approved keys. This key will be able to act as Bob.”
- [x] Bind the displayed account and target key to the signed action, and recheck authorization when accepting it.
- [x] Give denied requests an explanation of which approval path is available, without allowing the interface to bypass server enforcement.

Stage 1 binds the target key to the signed record and rechecks live authorization under the write lock. Stage 2 adds the displayed consequence, key-count warning, exact key confirmation, signed review-context digest, and renewed review after context changes.

Meaningful authorization prompts should identify the consequential details the user is authorizing. This follows [OWASP transaction authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html).

## Priority 2 Show account key history and exceptional approvals

- [x] Show each approved key on the account page, with its approver and approval time where known.
- [x] Distinguish first-key sponsorship, an additional key authorized by the same account, and an additional key authorized by somebody else. Identify operator overrides explicitly.
- [x] Record that classification when a new approval is accepted, along with the relevant key count and actor authority, so future reviews need not infer them from current state.
- [x] Preserve “unknown” for historical facts that cannot be established. The current audit's review candidates must not become confirmed violations merely by appearing in the UI.
- [x] Add account-key grants to an operator security feed with links to the affected account and approval evidence.
- [ ] Consider a community-visible event for additions to existing accounts: “Alice approved an additional key for Bob. Bob now has 3 approved keys.” Give ordinary new-member sponsorship a less prominent presentation.
- [x] Keep IP addresses and device details out of public events, and respect the site's membership/access boundary when exposing history.
- [x] Include invitation-derived grants in the same history and classification rules.

Existing foundations: individual profiles show approval attribution, and the CLI audit includes invitation-derived grants, unknown attribution, and explicit warnings that review candidates are not proven violations. Policy evaluation computes classification/count/authority, and Stage 3 persists these facts in grant-bound acceptance receipts. Account-wide history is implemented in Stage 4; Stage 5 supplies the authenticated operator security view. Stage 6 supplies the [acceptance-evidence specification](../../specs/approval_acceptance_record_v1.md) and [deployment, recovery, and rollback runbook](../../runbooks/account_key_approval_safety.md).

## Priority 3 Make approval policy changes visible

- [x] Surface who enabled or disabled cross-account approvals and when, using the existing signed feature-flag change history where possible.
- [x] Let an operator review policy changes alongside account-key grants so a brief enable, approve, disable sequence is easy to notice.
- [x] Decide whether new approval events should reference the effective policy or policy-change record: approved Step 2 requires effective policy in durable acceptance evidence. Stage 3 implements that evidence.
- [x] Explain that disabling the flag restricts future acceptance and does not revoke keys already approved while it was enabled.

The registered flag description explains prospective behavior. Existing signed feature-flag activity supplies actor/time/source evidence; Stage 5 adds the authenticated operator view joining policy changes with grants, including signed actor/time/source evidence and explicit uncertainty for unrecorded deployment changes.

Historical policy context is useful audit evidence. It is not required to re-evaluate or remove previously accepted grants under the prospective restriction model.

## Priority 4 Notify existing account holders

- [ ] Show a persistent new-key notice to the account's existing devices, identifying the new grant and linking to account-key history.
- [ ] Use available notification channels without requiring every member to provide an email address.
- [ ] If an independent channel already exists, consider sending the notice there as well; an in-account notice alone depends on the owner returning to that account.
- [ ] Define a practical way to report an unfamiliar key and reach an operator.
- [x] Plan and implement effective ordinary-key revocation and subsequent session-access denial as a separate operator-controlled response capability. See the [revocation runbook](../../runbooks/account_key_revocation.md). Do not present a report or dismissal action as if it removes access.
- [ ] Keep the limitation explicit: notification can reveal unexpected access, but cannot undo private information already read.

Notifying users when authentication factors change is consistent with [OWASP factor-change guidance](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html).

## Priority 5 Make owner authorized device setup convenient

- [ ] Design a short-lived pairing request that a member can open or scan on an already approved device.
- [ ] Bind the request to one account and the exact new key, and require proof that the new device controls that key.
- [ ] Show an explicit confirmation on the existing device before it authorizes the addition.
- [ ] Make the request single-use and reject expired, modified, or replayed requests.
- [ ] Complete the flow with a visible account-key history entry and the same notification behavior as other key additions.
- [ ] Provide useful expired-request and retry behavior without falling back to unrestricted cross-account approval.

The goal is to make adding your own device easier than asking another member to approve a key claiming your username. Recovery without an available approved device remains a separate design.

## Decisions before selecting follow-up cycles

- [x] Choose the initial visibility scope: approved Step 2 includes account history and a root-approved operator view, with no new public feed. Community-visible events remain a follow-up consideration.
- [ ] Choose notification channels and how existing devices recognize and acknowledge new-key notices.
- [x] Define the historical evidence available for older approvals and how uncertain classifications are presented.
- [x] Scope a revocation response before promising users they can disable an unfamiliar key. Operator-only ordinary-key revocation is implemented; member self-service remains deferred.
- [x] Review the combined Step 1 scope and select its FDP cycle: approved Option B groups priorities 1–3; notifications, pairing, and revocation remain deferred.

## Verification for selected work

Enforcement verification: `php tests/run.php ApprovalPolicyTest` passed all six tests, and `php tests/run.php WriteApiSmokeTest::testSignedInvitationRedemptionRechecksPolicyBeforeAcceptance` passed during the checkout review. These cover the permission matrix, live policy changes, competing claims, stale state, protocol bypass attempts, operator authority, and invitation acceptance. The completed Stage 6 summary records additional signed API, browser, history, access-boundary, archive/rebuild, and local release checks. The checks below reflect that recorded evidence; they were not rerun during the October 23 checklist reconciliation. The full repository suite is not claimed to pass; the summary preserves prior failures and startup-timeout rerun results.

- [x] Confirm onboarding stays straightforward while additional-key approval clearly communicates account access.
- [x] Confirm approval prompts match the signed target and the final accepted action, including stale or concurrent requests.
- [x] Confirm direct approvals, invitations, and operator overrides receive consistent history and visibility treatment.
- [x] Confirm previously accepted grants retain their historical classification when current key counts or the flag change.
- [x] Confirm audiences cannot see account or device information beyond their authorized scope.
- [ ] Confirm any pairing flow rejects expired/replayed requests and cannot substitute a different account or key. Deferred with Priority 5; no pairing flow is included in this release.

Verification evidence: Stages 1–2 cover permission, stale-context, target-binding, and concurrent-claim checks; Stages 3–4 cover acceptance snapshots, invitation/archive replay, history, and membership boundaries; Stages 5–6 cover authenticated operator access, cache/offline boundaries, signed browser flows on desktop/mobile, and local deployment rehearsal. See the [implementation summary](../account_key_approval_safety/account_key_approval_safety_step4_implementation_summary.md) for exact commands, results, and limitations. No application, dependency, or production changes were made during checklist reconciliation.

Visibility supports detection and accountability. Enforcing who may grant account access remains the primary prevention measure.

Revocation verification: the separate cycle records signed terminal denial, retained posts/direct/invitation sponsorship and username claims, explicit cutoff versus acceptance, stale review, committed retry, schema-25 startup recovery, current archive restore/rebuild, CLI output, and phone/desktop browser checks across 16 themes. No production rollout or custom historical cutoff behavior is claimed.
