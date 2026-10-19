> **Feature plan:** [Step 2](./user_approval_meaning_step2_feature_description.md) · [Step 3](./user_approval_meaning_step3_development_plan.md) · [Step 4](./user_approval_meaning_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Feature 03 in the [Zenmemes feedback summary](../zenmemes_meta_feedback_2026-10-18_summary.md) asks that approving a user explain the approver is vouching for that person's conduct and integrity. Current approval review explains account-key access but omits this social responsibility. Feature 01 overlaps delivered composer cleanup; feature 02's personality slice is complete and multiple agents is explicitly deferred. This cycle takes the next independent feature in summary order. Step 1 is skipped because the requested outcome is narrow. This cycle is not selected into the optional effort study.

## User story

- As an approved member reviewing a new member, I want to understand what my approval means so I can make an informed decision.

## Core requirements

- Explain before confirmation: “By approving this user, you vouch for their conduct and integrity.”
- Keep the existing account-key access consequence visible; vouching does not replace the security explanation.
- Distinguish new-member approval from adding an account key; avoid suggesting that adding one's own key vouches for a new person.
- Reuse the shared approval review across entry points, without extra dialogs, repeated page-wide help, or changes to authorization and signing.

## Delivery scope and completion boundary

Work type: application change, limited to approval-review presentation, appropriate focused verification, and the UI-copy audit checklist. No schema, navigation, permission, or approval-record changes. Enter through an eligible profile or pending-user approval action, review the social and account-access consequences, then confirm or cancel. Cancellation leaves approval untouched; an existing signing/submission failure retains its current feedback and retry path. Release requires consistent review through the actual shared entry points and preservation of existing key-specific warnings. No deployment is included.

## Shared component inventory

- `ApprovalPolicy::review`: canonical review label and consequence, reused by the prepared approval response and browser confirmation.
- `partials/profile_access.php`: actionable User Access profile review; extend existing contextual consequence rather than add standalone help. The standard profile links to User Access.
- `pages/users_pending.php` and `pending_approvals.js`: standard and User Access pending lists route approval through shared browser signing.
- `browser_signing.js`: shared confirmation used by signed approval forms and pending actions; preserve confirmation/cancellation behavior.
- Standard and Forte profile displays: inspected templates have no direct approval form; map any other Forte approval actions during development planning and cover any actionable path through the same review contract.

## Risks

- Mislabeling account-key grants could mislead users. Before Step 3, map the three existing review classifications and limit social-vouching wording to new-member approval.
- Duplicating copy could obscure security consequences. Before Step 3, confirm the review's server and browser consumers and use the existing consequence placement.
- A missed entry point could omit the explanation. Before Step 3, enumerate actionable approval routes, including Forte and User Access, and identify shared coverage.

## Flow and success criteria

1. An eligible member opens approval from a profile or pending list.
2. The existing review explains new-member vouching and account-key access before confirmation.
3. The member cancels without approving or confirms through the existing signed flow.

Success means every new-member approval review communicates conduct and integrity; account-key-only reviews remain accurate; existing access warnings, cancellation, signing, and failure recovery work as before. Record the contextual-copy decision in `docs/plans/ui_copy_audit_checklist.md`.
