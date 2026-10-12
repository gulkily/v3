> **Feature plan:** [Step 1](./operator_privilege_delegation_step1_solution_assessment.md) · [Step 2](./operator_privilege_delegation_step2_feature_description.md) · [Step 3](./operator_privilege_delegation_step3_development_plan.md) · [Step 4](./operator_privilege_delegation_step4_implementation_summary.md)

## Original Query

As an admin-approved operator, I want to be able to pass this privilege to other users. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Understood Intent

Treat “admin-approved” as the application's existing root-approved operator status. Interpret “pass” as granting another user operator authority while retaining the granting operator's own authority; transferring or surrendering the role is not assumed.

**Follow-up direction:** Favor username-wide roles for a low-value instance, reuse signed tagged bootstrap replies, and derive live authority from stored grant relationships. Restoring an operator must restore all their unrevoked grants, including downstream authority. See [brainstorm notes](./operator_privilege_delegation_brainstorm_notes.md).

## Problem

Operator access currently depends on direct root approval, so an operator cannot explicitly grant another user the same authority through the application.

## Options

### Option A: Key-specific delegation with cascading revocation

Operators issue explicit signed tagged grants to individual identities. Recipients can delegate further; authority depends on an active grant path from root, so losing and restoring that path cascades through unrevoked grants.

- Pros: preserves current key-specific scope; contains exposure from additional account keys; mirrors stored grant relationships.
- Cons: each device or recovered key needs a separate operator grant; requires graph rules and consistent authorization updates.

### Option B: Username-wide delegation with cascading revocation

Operators grant the role to a username through a signed bootstrap reply with a distinct operator-grant meaning. Every approved key for that account can act and delegate; authority follows active grant paths from root.

- Pros: one role grant per person across devices; reuses approval infrastructure; matches the live-site/data relationship and desired restoration behavior.
- Cons: approving an account key also gives it operator access; requires stricter key approval, stable account binding, and cascade previews.

### Option C: Username-wide delegation with independent removal

Operators explicitly grant username-wide roles, but recipients retain authority after the grantor loses it; each role must be removed independently.

- Pros: predictable individual removal; simpler effective-role calculation.
- Cons: authority no longer follows live paths to root; downstream cleanup is manual; does not meet the chosen cascading behavior.

## Recommendation

Choose **Option B**. Keep membership approval separate, accept operator grants only from authorized operators, and preserve signed grant/revocation history. A tag alone cannot confer authority.

- Derive authority by following active grants from root anchors; alternate paths preserve access and disconnected cycles do not.
- Revoking a grant suspends downstream authority where no alternate path exists. Preserve outgoing grants; restoring the path reactivates all unrevoked descendants. Explicitly revoked grants remain revoked.
- Reserve adding keys to operator accounts to operators/admins or an approved key of that account; do not approve pending keys automatically when granting the role.

**Vertical-slice viability:** An operator grants from a user page; the recipient's approved keys gain operator access and can delegate. Include attributed activity, grant-specific revocation with affected-user preview, cascading restoration, consistent enforcement across operator pages/writes, and root recovery.

**Resolve in Step 2:** Revocation permissions, stable username/account binding and root anchors, recipient eligibility, and key-approval/recovery policy. Grant-specific revocation is preferred; any account-wide removal must define how multiple incoming grants are handled.
