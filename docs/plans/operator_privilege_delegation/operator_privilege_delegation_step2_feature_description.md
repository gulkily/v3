> **Feature plan:** [Step 1](./operator_privilege_delegation_step1_solution_assessment.md) · [Step 2](./operator_privilege_delegation_step2_feature_description.md) · [Step 3](./operator_privilege_delegation_step3_development_plan.md) · [Step 4](./operator_privilege_delegation_step4_implementation_summary.md)

# Operator privilege delegation feature description

## Problem

Operator authority currently requires direct root approval of an individual key. Operators need to delegate through the user interface, with account-wide access and live authority reflecting stored grant relationships.

## User stories

- As an operator, I want to grant another user the same role so that they can administer and appoint operators without administrator intervention.
- As a recipient, I want my approved account keys to share operator access so that switching devices requires no additional role grant.
- As an operator, I want to preview and revoke a grant so that I understand downstream access changes.
- As an administrator, I want restoring an operator to restore their unrevoked grants so that recovery follows the recorded relationships.

## Core requirements

- **Account binding:** Grants connect canonical username accounts, anchored to existing bootstrap records; signing identifies the acting key. Recipients must have an approved key. Root-seeded identities anchor their accounts; all approved keys of an authorized account share authority. Display names alone cannot redirect a grant, and pending keys gain no access.
- **Explicit signed actions:** Reuse tagged bootstrap replies for distinct operator grants and grant-specific revocations. Preserve membership approval separately. Require an authenticated, approved key of a currently authorized operator account at acceptance; any operator may revoke any delegated grant. Root authority cannot be revoked through this flow.
- **Graph semantics:** Effective authority is reachability from root accounts through unrevoked grants. Alternate paths preserve access; disconnected cycles do not. Losing authority suspends outgoing grants without deleting them; restoration reactivates all unrevoked descendants. Explicit revocations remain effective, and replay cannot undo them; restoration requires a new incoming grant or root recovery.
- **Account safety:** Adding a key to any account with root status or operator-grant history requires a current operator/admin or an already approved key of that account, even while its role is suspended. This restriction applies regardless of ordinary cross-account approval settings. Role grants never approve pending keys.
- **Consistent effects:** Show role, grant provenance, and signed activity; preview accounts losing authority before revocation. Reject stale confirmations when effects change. Successful changes affect subsequent operator reads/writes consistently; full reconstruction produces the same authority as live updates.

## Delivery scope and completion boundary

**Work type:** Application change. Deliver grant, delegation, revocation, restoration, account-key protection, and shared authorization through existing user and operator surfaces. Exclude account renaming, broad membership-policy redesign, and account-wide denial controls.

**Normal entry/outcome:** An operator opens a user page, signs a grant, and the recipient's approved keys can use existing operator tools and delegate. **Recovery:** Revocation previews cascading loss; a new incoming grant restores suspended descendants; existing administrator seed/recovery commands remain available. **Release condition:** Both site presentations and operator read/write paths satisfy the same authority rules, including reconstruction and stale-action rejection.

## Risks

- **Account confusion:** Username grouping could grant the wrong keys. **Early validation:** confirm canonical grouping and bootstrap binding. **Mitigation before Step 3:** fix account/root identity rules and exclude renaming.
- **Recovery escalation:** New keys could inherit privileged access. **Early validation:** trace every membership approval/invitation entry. **Mitigation before Step 3:** apply operator-account protection across all entries, including suspended accounts.
- **Graph or stale-state errors:** Cascades could leave unauthorized access or revoke unexpected users. **Early validation:** review alternate paths, cycles, restoration, and concurrent confirmations. **Mitigation before Step 3:** define one shared authority/preview contract and reconstruction parity; unresolved risks block dependent stages.

## Shared component inventory

- **User/profile surfaces:** Extend classic and Forte directories, username pages, individual profiles, and profile summary/detail panes with shared role/provenance and action presentation.
- **Identity surfaces:** Extend profile API, account-key/pending-approval views, signed approval controls/APIs, and invitation acceptance with common account eligibility; reuse browser signing.
- **Activity surfaces:** Extend classic/Forte activity rows, detail panes, and commit manifests to present grants/revocations and attribution.
- **Operator surfaces:** Extend Tools navigation, feature-flag pages/APIs, visitor statistics authorization, and Codebase State operator listing to use effective authority. Keep direct-root attribution distinct.
- **New shared controls:** Grant/revocation confirmation and cascade preview are needed because ordinary approval controls do not express role changes or downstream effects.

## Simple user flow

1. Open a user page and review account/operator status.
2. Select grant or a specific existing grant to revoke; review scope and affected users.
3. Sign and submit; changed authorization/effects require renewed review.
4. See attributed activity and effective access; regrant after revocation to restore unrevoked descendants.

## Success criteria

- Two approved keys of one recipient share operator access; its pending key does not.
- Root → Alice → Bob → Carol loses Bob/Carol when Alice's grant to Bob is revoked; Carol retains access with another root-connected path.
- Regranting Bob restores Carol and unrevoked descendants, while revoked grants and disconnected cycles confer no access.
- Ordinary members cannot add keys to protected operator accounts; UI, APIs, live updates, and reconstruction agree on authorization.
