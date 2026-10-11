> **Feature plan:** [Step 1](./account_key_approval_safety_step1_solution_assessment.md) · [Step 2](./account_key_approval_safety_step2_feature_description.md) · [Step 3](./account_key_approval_safety_step3_development_plan.md) · [Step 4](./account_key_approval_safety_step4_implementation_summary.md)

## Problem

Approvals can grant access as an existing user without clearly stating that consequence or preserving an understandable account-access history.

## User stories

- As an operator, I want restricted cross-account grants so that sponsorship cannot grant access to existing accounts.
- As an approver, I want explicit consequences so that I authorize the intended account and key.
- As an account holder or operator, I want grant history so that I can investigate unfamiliar keys.

## Core requirements

- **Policy:** One operator-controlled flag allows non-operator cross-account approval; approved default: enabled for compatibility. Disabled blocks non-operators adding keys to different existing usernames; first-key sponsorship, own-username additions, and root-approved operator grants remain allowed. Existing means another approved key shares the canonical username at acceptance. Preserve existing validation across direct/invitation requests, including stale/concurrent attempts.
- **Explicit authorization:** Use the labels below with actual usernames. For another existing account, show: “Bob already has 2 approved keys. This key will be able to act as Bob.” Signed targets must match; changed authorization context requires renewed review.
- **Durable evidence:** Retain authorizer, target account/key, acceptance time, classification, key-count context, operator authority, and effective policy for new direct/invitation grants. Historical uncertainty stays unknown; audit candidates are not proven violations. Toggles/rebuilds never retroactively remove accepted grants.
- **Account visibility:** Show grants, operator overrides, known timestamps, and evidence links under existing profile visibility rules; no IP/device details or new public feed.
- **Operator visibility:** Provide root-approved operators a focused activity view joining grants and recorded flag changes. Explain effective policy; unrecorded deployment-change history remains unknown.

| Situation | Required wording |
| --- | --- |
| First key for a new username | Approve new member Bob |
| Additional key for your username | Add this key to my account |
| Additional key for another existing username | Grant this key access as Bob |

## Delivery scope

Application change implementing approved Option B. Defer persistent notifications, external delivery, pairing, revocation, session invalidation, recovery, and account-model redesign.

## Completion boundary

Existing settings/approval entry → authorized grant and history, or explained denial. Failed/stale requests support refresh/review/retry without duplicate grants. Release requires verified direct/invitation outcomes, access boundaries, and rebuild consistency within one day/eight stages; otherwise rescope for approval.

## Risks

- **Bypasses or races:** Unintended access; validate all entries and simultaneous claims before Step 3; require consistent acceptance checks.
- **Misleading history:** False accusations/access loss; validate legacy/invitation evidence before Step 3; preserve unknowns and accepted context.
- **Scope or disclosure:** Oversized work/private disclosure; validate surfaces/audiences before Step 3; retain access boundaries and require approval to split scope.

## Shared component inventory

| Existing surfaces | Treatment |
| --- | --- |
| Feature-flags page and signed change API/history | Extend existing flag controls and evidence |
| Classic pending/profile approval controls; approval prepare/finalize APIs; invitation issue/redemption flows | Extend shared decision and wording; preserve existing failure handling |
| Classic/Forte username, profile, user-directory and profile-summary views; profile API | Reuse shared profile data; extend full account history and keep summaries consistent |
| Classic/Forte activity lists, filters, details and evidence links | Extend with operator review; no separate feed framework |
| Approval CLI/audit, canonical evidence, full/incremental projections and cached profile/activity output | Reuse authority/evidence sources; keep decisions, history, and diagnostics consistent |

## Simple user flow

1. Operator selects the effective approval policy.
2. Member reviews the appropriate account-specific wording and signs.
3. Request succeeds or explains denial/staleness with a retry path.
4. Owner inspects key history; operator correlates grants and policy changes.

## Success criteria

Verify all three labels; both flag values across actor/target and direct/invitation combinations; concurrent first-claim protection; grants/history preserved after toggles/rebuilds; operator-review access control.

## Review

**Approved Step 2** received, including the default-enabled flag. Proceed to Step 3 planning within this scope.
