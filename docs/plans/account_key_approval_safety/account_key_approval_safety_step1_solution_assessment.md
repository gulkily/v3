> **Feature plan:** [Step 1](./account_key_approval_safety_step1_solution_assessment.md) · [Step 2](./account_key_approval_safety_step2_feature_description.md) · [Step 3](./account_key_approval_safety_step3_development_plan.md) · [Step 4](./account_key_approval_safety_step4_implementation_summary.md)

## Original Query

Please accumulate as much of this checklist as is reasonable into Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Understood Intent

Consolidate the [approval visibility and safety checklist](../backlog/account_key_approval_visibility_and_safety_checklist.md) into a cohesive release that reduces account impersonation while preserving convenient onboarding and owner key additions; reconsider the earlier flag-only boundary without committing to the whole roadmap.

## Problem Statement

Approvers can unintentionally grant access to existing usernames, while account holders and operators lack a clear history explaining those grants and the policy that permitted them.

## Option A Restriction and clear approval prompts

Deliver the cross-account approval flag with prompts distinguishing new-member sponsorship, adding your own key, and granting access as another existing username.

- Pros: Smallest complete preventive change; preserves the established first-release boundary.
- Cons: Leaves account-key history and policy-change visibility for another release.

## Option B Restriction with approval history and policy visibility

Combine Option A with account-key history and a focused approval/policy activity view using existing account and activity surfaces, covering checklist priorities 1–3.

- Pros: Users understand the action before approving; owners and operators can inspect grants and correlate them with policy changes afterward.
- Cons: Requires reliable acceptance-time context and consistent history across direct approvals, invitations, and rebuilds; broader than the flag alone.

## Option C Full account access management

Add persistent device notices, device pairing, key revocation, and session invalidation to Option B.

- Pros: Adds convenient device setup and an effective response to unfamiliar keys.
- Cons: Introduces multiple independent lifecycles; exceeds a reasonable single FDP cycle.

## Recommendation

Choose **Option B**, subject to the FDP size limit; retain Option A as the fallback if planning cannot bound the combined outcome.

- **Prevent and explain:** Apply the restriction to all member approval paths, including invitations. Preserve new-user sponsorship, same-username key additions, and root-approved operator authority. Show the target account, existing key count, and access consequence before authorization; explain denials and retry paths.
- **Preserve evidence:** For new grants, retain who approved which key, when, approval classification, key-count context, operator authority, and effective policy at acceptance. Keep uncertain historical facts explicitly unknown; do not relabel audit candidates as violations or revoke accepted grants when the flag changes.
- **Expose history:** Show account-key grants and operator overrides with evidence links. Bring grants and recorded flag changes together for operator review. Recommend highlighting additional-key events only within existing site access boundaries, with no public IP/device details or new public security feed. Unrecorded deployment changes remain unknown.
- **Bound scope:** Reuse existing approval, account, and activity experiences. Defer persistent/device-specific notifications, external delivery, pairing, revocation, session invalidation, social recovery, and account-model redesign. A contact-operator link may help with unfamiliar keys but must not imply access was revoked.
- **Vertical-slice viability:** Operator configures policy → member reviews and submits → server permits or explains denial → owner/operator inspects durable history. Include concurrent/stale requests, invitation enforcement, access boundaries, and rebuild consistency. Before Step 3 approval, require one day or at most eight stages; otherwise split visibility into the next cycle without weakening enforcement.
- **Decision for Step 2:** Recommend allowing cross-account approvals by default for compatibility, with operators opting into restriction; the default remains unconfirmed.

## Review

**Approved Step 1** received. Option B is the basis for Step 2; the proposed flag default remains part of Step 2 review.
