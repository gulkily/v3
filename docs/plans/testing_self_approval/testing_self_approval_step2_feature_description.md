> **Feature plan:** [Step 1](./testing_self_approval_step1_solution_assessment.md) · [Step 2](./testing_self_approval_step2_feature_description.md) · [Step 3](./testing_self_approval_step3_development_plan.md) · [Step 4](./testing_self_approval_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Maintainers repeatedly need to approve their browser account on temporary localhost instances. Copying an identity into a terminal or adding startup options interrupts testing.

## User stories

- As a maintainer, I want to enable a testing shortcut with a flag file so that it stays available across local server restarts.
- As a maintainer, I want to approve my current account with one click so that I can immediately test approved-member behavior.

## Core requirements

- An ignored regular file named `.test-self-approval` in the project root enables the shortcut; contents do not matter. Absence disables it. Creation and removal take effect without a restart, including enforcement when submitting an already-open page.
- Show **Approve current account** on the existing Account Key page only for an authenticated, unapproved account and an eligible local request. No identity entry, extra confirmation, startup flag, or environment variable is required.
- The server independently requires the marker, a direct loopback connection, and a protected same-origin state-changing request authenticated as the target account. Hostnames and forwarded headers alone cannot establish eligibility; public proxy access is not supported by this shortcut.
- Reuse the root-seeded approval behavior of `./v3 approve`, including immediate visible approval, existing canonical persistence, and retry/repair behavior. Never approve a client-selected different account. Already-approved submissions are harmless.
- Keep copy to the action and essential result/recovery feedback. Use existing account controls and feedback styling; record the UI copy review in `docs/plans/ui_copy_audit_checklist.md`.

## Delivery scope and completion boundary

Work type: **application change**, including focused automated verification and usage documentation. Deliver marker gating, the Account Key action, approval refresh, and recoverable errors through the normal browser flow. No new navigation destination, general approval administration, automatic approval on account creation, or read-model schema change is intended. Completion requires verification of both successful local approval and rejected ineligible requests.

## Known user and environment

The maintainer creates the marker once per checkout, starts the temporary localhost instance normally, and opens `/account/key/` after setting up or restoring their browser account. Verify ordinary localhost startup in this WSL workspace and browser account setup through approval; record the actual connection path used. If the action fails, show whether approval persisted and the existing repair or retry action; after repair, the maintainer retries and verifies approved status and member access. Non-loopback proxy paths remain ineligible rather than trusting forwarded headers. Browser/WSL verification that cannot be performed must be reported as pending, not inferred from another platform.

## Risks

- **Unintended approval exposure:** marker gating alone could expose the action remotely. Validate local connection and authenticated-request boundaries before UI integration; reject remote and spoofed-host requests server-side.
- **WSL connectivity mismatch:** a browser may reach WSL through a non-loopback forwarding path and lose the shortcut. Inspect the actual ordinary localhost path early in planning; resolve any unsupported required path before dependent implementation.
- **Partial write or stale access:** a persisted seed could be followed by refresh failure. Reuse existing persistence/recovery behavior and verify immediate member access, repeated clicks, and a recoverable failure.

## Shared component inventory

- Account Key page: extend its current account actions and feedback; do not add another account screen.
- Lobby and profile approval displays: retain their existing approval presentation and verify they observe the result; the lobby's existing account link is the entry route.
- Existing browser identity/session handling and request protection: reuse for current-account authentication and feedback.
- CLI seed writer: reuse the canonical operation behind `./v3 approve`; existing member-vouching and approval-review surfaces retain their distinct purpose.

## Simple user flow

1. Create `.test-self-approval` once in the checkout and start the local instance normally.
2. Set up or restore the browser account, then open Account Key and click **Approve current account**.
3. See approval immediately and use member features; follow visible repair/retry guidance if needed.
4. Remove the marker to disable further testing self-approvals. Existing approvals remain in effect.

## Success criteria

One click approves the authenticated unapproved account with CLI-equivalent seed semantics and immediate member access. No extra startup configuration is needed. Missing/removed marker, remote connection, unauthenticated request, cross-origin request, and attempts to select another account cannot create approval. Marker removal blocks submission from an already-open page. No duplicate action or explanatory copy is introduced.

## Request record

- **Request:** less friction, then a flag file. **Disposition:** checkout-local ignored marker replaces per-start enablement. **Approval status:** Step 1 approved; this feature description awaits Step 2 approval. **Outcome:** one enabling file operation per checkout, one click per account.
