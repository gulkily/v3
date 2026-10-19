> **Feature plan:** [Step 1](./testing_self_approval_step1_solution_assessment.md) · [Step 2](./testing_self_approval_step2_feature_description.md) · [Step 3](./testing_self_approval_step3_development_plan.md) · [Step 4](./testing_self_approval_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Deliver the approved marker-gated Account Key action: create ignored `.test-self-approval`, start localhost normally, authenticate the browser account, click once, and immediately use approved-member features. Removing the marker blocks further requests without restart; existing approvals persist. Preserve CLI-equivalent seed persistence and repair/retry feedback. Verify actual WSL localhost browser entry and record the connection used; any unavailable Windows-host browser check remains explicitly pending. Release requires the normal browser flow, rejection cases, recovery, focused regressions, and usage/UI audit documentation. No deployment or production writes are required; no schema change is planned.

## Key Risks

- **High risk: unintended root approval.** A marker, hostname, or lobby identity alone is insufficient. Validate direct loopback, strong session authentication, and same-origin request protection before wiring the button; recheck all gates on submission.
- **High risk: persisted approval with stale access.** Reuse `seedApprovedIdentity`, retain persistence-specific repair messages, and replay refresh failure plus repair/retry before release.
- **WSL entry mismatch.** This workspace is WSL2 and has Chromium; actual launcher/browser connectivity is still unverified. Stage 1 validates localhost before UI work. Non-loopback forwarding stays unsupported; a required incompatible path blocks dependent work and returns to planning rather than weakening the gate.

## Stage 1

- Goal: establish and verify the testing eligibility boundary.
- Dependencies: Step 3 approval, feature branch, organized/committed approved plans.
- Expected changes: ignore the marker; add one shared eligibility policy for regular-file presence and direct loopback requests, checking fresh filesystem state. Define `isEnabledForRequest(): bool` for reuse by rendering and submission. No enabling marker is added to the checkout by the feature itself.
- Verification approach: focused tests for absent/present/nonempty/removed marker, directory rejection, IPv4/IPv6 loopback, remote peers, and spoofed Host/forwarded headers; start ordinary `./v3 start` on a temporary port and record WSL browser connectivity before proceeding.
- Risks or open questions: impact is unsafe exposure or unusable local entry; validate request addresses early and block dependent UI work if the required path is incompatible.
- Canonical components/API contracts touched: new shared testing eligibility policy, project ignore rules, existing development launcher and browser harness for verification only.

## Stage 2

- Goal: complete the one-click account approval flow.
- Dependencies: Stage 1 verified and committed; locality/authentication risks resolved.
- Expected changes: add a protected current-account POST action and conditional Account Key button using existing controller/rendering conventions, authenticated viewer resolution, browser authentication, feedback, and styles. Reuse the seed writer with the CLI default reason; accept no alternate target or reason. Return current approval state, refresh session/member access, handle already-approved retries, and retain actionable failure feedback. No shell execution or new navigation.
- Verification approach: exercise the browser setup/authentication-to-click flow on isolated data; verify canonical seed semantics and immediate member access. Reject wrong method, no marker, remote peer, missing/weak authentication, invalid request protection, cross-origin submission, and alternate-target inputs. Test repeat submission and marker removal after rendering.
- Risks or open questions: impact is approval of the wrong identity or stale member access; verify session-derived target and refresh behavior before committing. Existing browser identity restoration must reveal the action without an extra manual setup step.
- Canonical components/API contracts touched: `Application` routing/member-access allowance, Account Key controller/template and browser behavior, authenticated viewer/session handling, `LocalWriteService::seedApprovedIdentity`, existing feedback components. Add only the minimal dedicated action contract required.

## Stage 3

- Goal: prove recovery and finish maintainer guidance.
- Dependencies: Stage 2 verified and committed.
- Expected changes: extend focused approval/browser regressions for partial persistence and repair/retry; document marker creation/removal and direct-localhost limitation in existing usage guidance; update `ui_copy_audit_checklist.md` and implementation summary.
- Verification approach: replay refresh failure after persistence, show repair guidance, repair and retry without duplicate/conflicting seeds; verify Account Key, lobby, and profile/member access agree. Run approval-seed and authentication regressions plus desktop/mobile browser checks in WSL. Verify CLI approval still works, marker isolation/cleanup, no overflow, and document any Windows-host verification still pending.
- Risks or open questions: impact is claiming recovery or environment coverage without evidence; capture actual checks and pending limitations separately. Removing the marker disables the shortcut, not approvals already written.
- Canonical components/API contracts touched: existing seed/auth regression suites, browser fixtures, CLI/local-server documentation, UI copy audit; no new production behavior beyond Stage 2.

## Execution and request record

Keep this plan uncommitted until **Approved Step 3**. Then create a feature branch, organize this cycle under `docs/plans/testing_self_approval/`, update the planning index, and commit approved plans/debug log before creating the implementation summary. Each stage ends with verification, summary update, and its own commit before the next stage.

The less-friction and flag-file requests are incorporated in approved Step 2; no scope change is proposed. Stage 3 approval is pending.
