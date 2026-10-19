> **Feature plan:** [Step 2](./user_approval_meaning_step2_feature_description.md) · [Step 3](./user_approval_meaning_step3_development_plan.md) · [Step 4](./user_approval_meaning_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Step 2 approved in conversation. From User Access profiles or the shared pending-user list (legacy and Tools routes), new-member review states “By approving this user, you vouch for their conduct and integrity.” before signing. Existing key-access consequences and key identity remain visible; own-key and cross-account-key grants retain their distinct meanings.
- Confirming completes the existing signed approval; cancelling signs/publishes nothing and permits retry. Preparation/signing/submission failures retain actionable feedback and permit retry. Verify normal rendered entry points and the real signed flow using isolated fixtures, without production writes.
- Scope: shared review presentation and pending-list wiring, focused existing checks, UI-copy audit, and cycle artifacts. No schema, navigation, authorization, record, or deployment changes. Release requires the checks below; reverting this presentation change requires only a code rollback.

## Key Risks

- **High risk:** pending-list actions currently pass `confirm: false`, bypassing review entirely. Source inspection found this before implementation; remove the bypass and verify cancellation leaves the row and signing/publication untouched. This reuses the existing dialog required by Step 2, rather than adding a second review.
- Misleading key-grant wording: source confirms `new_account`, `own_account_key`, and `cross_account_key` classifications. Limit the social sentence to `new_account`; check all three and retain existing cross-account key-count/access warnings.
- Missed surfaces or duplicate help: shared profile partial consumes `ApprovalPolicy::review`; prepared API feeds the same review to browser signing. Standard/Forte profile templates have no direct approval form. Validate pending-route wiring and rendered User Access review, retaining contextual consequences rather than page-wide help. Any newly discovered independent approval path must be resolved before completion.

## Stage 1

- Goal: deliver the complete review-and-approve slice across existing actionable entry points.
- Dependencies: explicit Approved Step 3; create a feature branch, organize this cycle under `docs/plans/user_approval_meaning/`, repair navigation/index references, and commit approved plans and existing debug log before implementation.
- Expected changes: extend the canonical new-member review consequence with the approved sentence; route pending actions through the existing shared confirmation; update the UI-copy audit after verification. Keep labels, key-access warnings, signing, and permission policy intact. Extend existing checks only where they protect classification, review delivery, or cancellation/recovery behavior.
- Verification approach: run `ApprovalPolicyTest` and `UserAccessTest`, plus affected prepared-approval API checks; run the existing `approval_review.mjs` at desktop/phone widths with pending confirmation/cancel/retry and failure/retry coverage; run `account_key_approval.mjs` for real HTTP signed approval, three classifications, cancellation/retry, and rebuild persistence. Inspect the rendered new-member consequence and key-grant states on User Access at 390px/1280px. Run relevant PHP/JS syntax and diff checks. Record focused results and any baseline failures without claiming a full-suite pass.
- Risks or open questions: the existing browser fixture expects pending approvals without confirmation; update that expectation and verify one shared confirmation per attempt. If required checks fail, diagnose within scope before declaring completion; materially changed scope returns for approval.
- Canonical components/API contracts touched: `ApprovalPolicy::review` consequence only (no signature or payload-shape change); `pending_approvals.js` caller of `ForumBrowserSigning.submitSignedApproval`; existing profile partial and prepare/finalize API remain consumers. No database changes.
- Completion: update Step 4 summary and UI-copy audit with evidence; commit this stage with its summary before reporting completion. No deployment or external-service verification applies to this local presentation change.

## Request disposition

- Request: Approved Step 2 / Disposition: proceed to development planning within approved scope / Approval status: Step 2 approved; Step 3 pending / Outcome: this plan. No additional enhancement requested; optional effort study remains unselected.
