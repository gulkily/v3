> **Feature plan:** [Step 1](./feature_flags_security_review_cleanup_step1_solution_assessment.md) · [Step 2](./feature_flags_security_review_cleanup_step2_feature_description.md) · [Step 3](./feature_flags_security_review_cleanup_step3_development_plan.md) · Step 4 pending

Debug mode: enabled

Effort study: `railspike-effort` / `v3/feature_flags_security_review_cleanup` · [Ledger](../effort_estimation_trial.md#enrollment-index-after-p2)

# Access history in User Access

## Problem

A detached Feature Flags audit link leads to a one-line denial or sparse history. Operators need access events and evidence beside account management.

## User stories

- As an operator, I want access history beside account management so that I can review grants and policy changes where I manage access.
- As an operator, I want concise events with expandable evidence so that I can scan changes and inspect authority when needed.
- As a returning operator, I want a clear authentication action when my session expires so that I can resume the review without a dead-end page.

## Core requirements

- Place **Access history** within Tools → User Access, following the approved mockup's structure. Remove the standalone Feature Flags paragraph and loose User Access security-review link. Keep existing account/pending-approval actions and policy controls usable.
- Show up to five most recent relevant events in reverse chronological order: account-key approvals, accepted invitation grants and cross-account approval-policy changes. Each entry shows the action, affected account when applicable, actor and timestamp. Preserve unknown values honestly.
- Expand an event to inspect existing authority, policy-at-acceptance and source/receipt evidence where available. Keep historical-uncertainty warnings visible with that evidence; do not infer missing authority or acceptance facts.
- Provide **View all** when older events exist, leading to the complete existing audit history with the same concise event presentation and expandable evidence. Preserve existing audit URLs and full-history paging. Show a concise empty state when no matching events exist.
- Expose history only to authenticated root-approved operators. A hint-only or expired operator session offers the existing authentication path and a way back to history without exposing events; non-operators see no history control or event data. Direct protected URLs retain authorization and no-store behavior.

## Delivery scope and completion boundary

Application change using existing audit data and account management; no new audit collection or policy behavior. Include cycle artifacts/mockup, effort ledger and UI audit evidence in documentation scope. Mockup controls and fabricated data are not product behavior. Deployment is excluded.

Complete the operator's User Access → event evidence → full history → return flow, including authentication/retry after session expiry. Identify and replay the reported one-line destination (denied access versus empty history) before claiming resolution. Release requires phone/desktop, keyboard, affected-theme, overflow, empty/populated, expanded, operator/hint-only/non-operator, session-recovery and paging checks. Record evidence and limitations; mockup screenshots are insufficient.

## Risks

- Private data leakage: validate session/cache protections first; retain canonical operator authorization and no-store behavior throughout.
- Misleading history: inspect grant, invitation, policy and legacy events early; preserve evidence and unknown classifications.
- Divergent recent/full history or dead-end recovery: validate shared presentation, bounded history, paging and authentication round trip before UI completion.

## Shared component inventory

Extend User Access and canonical security event/details presentation. Reuse Activity history queries, standard/Forte security views, pagination/detail endpoints, acceptance/source evidence and account authentication. Keep account-key/profile history and signed flag-change pages as related destinations. Remove the Feature Flags shortcut; preserve ordinary Activity. Added/renamed navigation requires icon mappings and theme verification.

## Flow and success criteria

1. Open User Access and scan recent Access history.
2. Expand evidence; use View all for older events and return.
3. Handle empty history or authenticate after session expiry; non-operators see no protected history.

Success: no detached paragraph; recent/full history agree; evidence and historical caveats remain accessible; entry/recovery checks pass without overflow or obscured actions.

## Approval and study snapshot

Step 1 scope v2 and mockup approved by “Great, Approved Step 1.” This Step 2 clarifies five recent entries and the full-history destination without expanding the approved outcome; implementation and Step 3 are not authorized. No additional requests are pending.

2026-10-21T20:11:34Z — `railspike-effort`, cycle `v3/feature_flags_security_review_cleanup`, Step 2, scope v2 clarified; start `0b9f5f8baba58b31251b6a6a6b228412784328fe`, R1/P2, Codex exposed single rater (verified model identifier unavailable): total **0.45 reaffirmed**. Includes prior inspection/mockup, remaining planning, implementation, role/session/shared-view verification, documentation and ordinary correction. Frozen R1 sources verified at Step 1; reference meaning unchanged. No independent rating, measured labor or accepted outcome claimed; awaiting Step 2 approval.
