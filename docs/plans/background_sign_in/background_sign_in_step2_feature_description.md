> **Feature plan:** [Step 1](./background_sign_in_step1_solution_assessment.md) · [Step 2](./background_sign_in_step2_feature_description.md) · [Step 3](./background_sign_in_step3_development_plan.md) · [Step 4](./background_sign_in_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/background_sign_in; [ledger](../effort_estimation_trial.md#cycle-v3background_sign_in), standing enrollment P2
Extended estimates: enabled
Status: Step 1 Option A approved; Step 2 proposed
**Cycle estimate: 0.35**

## Problem

Background session restoration on a public page can reload it after someone starts reading or interacting. Session convenience must preserve the person's current work.

## User stories

- As a returning reader, I want sign-in to finish quietly so that my reading position and open controls stay put.
- As a writer, I want typing, name edits and submissions to survive session restoration so that none of my work is interrupted or duplicated.
- As a visitor whose sign-in fails, I want to keep browsing and recover through existing sign-in controls so that a background failure does not block participation.

## Core requirements

- Eligible public-page background sign-in never navigates, reloads or replaces the document, on success, failure, retry or an already authenticated result. An overlapping explicit action must retain its own intended continuation without acquiring an incidental reload.
- Preserve text, selection, focus, scroll, open controls and pending actions. Background success adds no routine banner or layout shift; viewer-specific first paint remains client-decided, and later updates reflect only genuine state changes.
- Restore a usable session for both approved and unapproved registered identities; authentication and membership approval remain separate. Session-dependent controls must either reflect the change safely or use the session on the next intentional navigation.
- Preserve keyless/unregistered browsing, the shared-static-page exemption, Account/invitation flows, and intentional protected-page or members-only navigation. Do not publish unused keys in the background.
- Failed background sign-in leaves work intact and existing explicit recovery available. An explicit retry reports actionable failure where needed; successful retry restores access without discarding the interrupted work.

## Delivery scope and completion boundary

Work type: Application change, focused regression tests and documentation; reuse the existing authentication service and state notification rather than introduce another sign-in mechanism. Documentation includes this cycle's plans/debug log, study ledger, relevant handoff and UI verification record. Proposed dependency delta: zero; no schema or canonical-format changes.

Normal entry is a public thread with a registered browser key, absent session and absent/unusable identity hint. Completion means interaction continues through delayed authentication and the restored session works on the next protected action/navigation. Recovery uses existing Account/sign-in controls; the person can retry and verify access without losing their draft. Release requires the acceptance evidence below and a recorded list of unchecked environments. Production session settings and version-check reloads remain separate follow-ups.

## Risks

- Shared callers may depend on redirects: review caller intent before planning changes, then preserve explicit continuations separately from background completion.
- State can become stale during overlapping calls or identity changes: validate these cases early; never apply another identity's authentication result to the current UI.
- A preserved draft alone can hide a reload or a duplicated submission: acceptance must observe document continuity and action results, not merely restored text.

## Shared component inventory

Extend the canonical private-site authentication flow and existing in-place notification. Reuse existing challenge, authenticate and status APIs; saved-key readiness and identity hints retain their roles. Review public layout/resume markers, Account identity/status, Lobby/authentication-resume, protected-link navigation, access-history sign-in, invitation issuance/redemption, browser signing and Railspike workflow callers. Thread/composer/name controls are preservation targets, not new authentication surfaces. No new UI component is proposed.

## User flow

1. Open a public page and begin reading, typing, editing a name or submitting an action.
2. Background sign-in succeeds or fails while the same document and interaction remain active.
3. Continue normally with the restored session, or use existing explicit sign-in recovery when access needs it.

## Success criteria

Delayed-authentication browser checks prove zero background document navigations and preserved input, selection, focus, scroll and open controls; a pending submission completes once. Verify usable sessions for approved/unapproved identities, already authenticated results, failures/retries and overlapping callers. Replace the compose test's reload allowance with a continuity assertion. Check relevant initial/success/failure states on desktop and phone viewports, affected themes, Account/invitations and intentional protected navigation; distinguish emulation from physical-device evidence. Unregistered/keyless and static-page cases retain their exemptions. Record checks and limitations in the implementation summary.

## Approval and study checkpoint

Checkpoint: Step 2 handoff, 2026-10-28T23:41:05Z; 0.35 reaffirmed
Scope: v1 / Option A; baseline 49a9024412c7e7f6d9230fb7ed1e7b72acf4627e; R1 / P2
Rater: Codex, single exposed rater; Step 1 reference verification retained; additional caller inventory inspected. No scope change or new estimate warranted; pristine intake remains missing.
Authorization: Approved Step 1 received. No new enhancement requests. Awaiting Approved Step 2; no implementation, commits, measured effort or accepted outcome.

*— Codex · 2026-10-28 UTC*
