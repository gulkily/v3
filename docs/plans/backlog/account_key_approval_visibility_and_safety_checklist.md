# Account key approval visibility and safety checklist

Status: Proposed follow-up work. Items are not approved implementation scope or completed features.

Reduce easy account impersonation while preserving open registration, member sponsorship, and convenient device setup. Prioritize the cross-account approval restriction, clear approval wording, and visible account-key history. Notifications and device pairing can follow as separately scoped features.

The [approval policy FDP checklist](account_key_approval_policy_fdp_checklist.md) retains the single-flag first-release scope. This checklist captures additional ideas from the discussion; it does not expand that release. See also the [findings and recommendations](account_key_approval_policy_findings_and_recommendations.md). Select and scope follow-up features through the [Feature Development Process](../../fdp/FEATURE_DEVELOPMENT_PROCESS.md).

## Priority 1 Prevent unintended access and clarify approvals

- [ ] Deliver the restriction flag through its existing FDP checklist, including invitation enforcement and acceptance-time checks.
- [ ] Preserve member sponsorship of new usernames, approval of additional keys for the approver's own username, and root-approved operator authority when the restriction is active.
- [ ] Use action-specific approval wording instead of a generic approval button.

| Situation | Suggested wording |
| --- | --- |
| First approved key for a username | Approve new member Bob |
| Additional key for the approver's username | Add this key to my account |
| Additional key for a different existing username | Grant this key access as Bob |

- [ ] For an additional key to another existing account, show the current approved-key count and the consequence before signing: “Bob already has 2 approved keys. This key will be able to act as Bob.”
- [ ] Bind the displayed account and target key to the signed action, and recheck authorization when accepting it.
- [ ] Give denied requests an explanation of which approval path is available, without allowing the interface to bypass server enforcement.

Meaningful authorization prompts should identify the consequential details the user is authorizing. This follows [OWASP transaction authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html).

## Priority 2 Show account key history and exceptional approvals

- [ ] Show each approved key on the account page, with its approver and approval time where known.
- [ ] Distinguish first-key sponsorship, an additional key authorized by the same account, and an additional key authorized by somebody else. Identify operator overrides explicitly.
- [ ] Record that classification when a new approval is accepted, along with the relevant key count and actor authority, so future reviews need not infer them from current state.
- [ ] Preserve “unknown” for historical facts that cannot be established. The current audit's review candidates must not become confirmed violations merely by appearing in the UI.
- [ ] Add account-key grants to an operator security feed with links to the affected account and approval evidence.
- [ ] Consider a community-visible event for additions to existing accounts: “Alice approved an additional key for Bob. Bob now has 3 approved keys.” Give ordinary new-member sponsorship a less prominent presentation.
- [ ] Keep IP addresses and device details out of public events, and respect the site's membership/access boundary when exposing history.
- [ ] Include invitation-derived grants in the same history and classification rules.

## Priority 3 Make approval policy changes visible

- [ ] Surface who enabled or disabled cross-account approvals and when, using the existing signed feature-flag change history where possible.
- [ ] Let an operator review policy changes alongside account-key grants so a brief enable, approve, disable sequence is easy to notice.
- [ ] Decide whether new approval events should reference the effective policy or policy-change record to make this explanation reliable.
- [ ] Explain that disabling the flag restricts future acceptance and does not revoke keys already approved while it was enabled.

Historical policy context is useful audit evidence. It is not required to re-evaluate or remove previously accepted grants under the prospective restriction model.

## Priority 4 Notify existing account holders

- [ ] Show a persistent new-key notice to the account's existing devices, identifying the new grant and linking to account-key history.
- [ ] Use available notification channels without requiring every member to provide an email address.
- [ ] If an independent channel already exists, consider sending the notice there as well; an in-account notice alone depends on the owner returning to that account.
- [ ] Define a practical way to report an unfamiliar key and reach an operator.
- [ ] Plan effective key revocation and session invalidation as a separate response capability. Do not present a report or dismissal action as if it removes access.
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

- [ ] Choose which grant events belong in community activity, account history, and the operator security feed.
- [ ] Choose notification channels and how existing devices recognize and acknowledge new-key notices.
- [ ] Define the historical evidence available for older approvals and how uncertain classifications are presented.
- [ ] Scope a revocation response before promising users they can disable an unfamiliar key.
- [ ] Select the first follow-up outcome and its FDP cycle without bundling the full checklist into the initial flag release.

## Verification for selected work

- [ ] Confirm onboarding stays straightforward while additional-key approval clearly communicates account access.
- [ ] Confirm approval prompts match the signed target and the final accepted action, including stale or concurrent requests.
- [ ] Confirm direct approvals, invitations, and operator overrides receive consistent history and visibility treatment.
- [ ] Confirm previously accepted grants retain their historical classification when current key counts or the flag change.
- [ ] Confirm audiences cannot see account or device information beyond their authorized scope.
- [ ] Confirm any pairing flow rejects expired/replayed requests and cannot substitute a different account or key.

Visibility supports detection and accountability. Enforcing who may grant account access remains the primary prevention measure.
