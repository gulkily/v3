> **Feature plan:** [Step 1](./background_sign_in_step1_solution_assessment.md) · [Step 2](./background_sign_in_step2_feature_description.md) · [Step 3](./background_sign_in_step3_development_plan.md) · [Step 4](./background_sign_in_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/background_sign_in; [ledger](../effort_estimation_trial.md#cycle-v3background_sign_in), standing enrollment P2
Extended estimates: enabled
Status: Step 2 approved; Step 3 proposed
**Cycle estimate: 0.35**

## Completion Contract

Open a public thread with a registered browser key and absent session/usable hint; keep reading, typing, editing a name or submitting while sign-in completes without document replacement or lost state. Prove the session works on the next protected action, and failure leaves existing explicit retry usable. Release requires the checks below with omissions recorded. Deployment and physical-device validation remain separately reported, never inferred from local emulation. No version-check/session-retention work, schema changes, new dependencies or publication of unused keys.

## Key Risks

- **High risk:** Redirects can destroy interaction or duplicate submissions; reproduce delayed authentication before changing it and assert document continuity plus one accepted action.
- Shared-flight options can swallow explicit navigation or apply a stale identity; establish caller/identity behavior in Stage 1 tests and resolve races before browser acceptance.
- Authentication does not imply approval; preserve truthful results and existing protected-access decisions rather than infer membership from a saved key.

## Stage 1 — Public sign-in stays in place

**Estimate: 0.12**

- Goal: Complete ordinary public session restoration without navigation.
- Dependencies: Approved Step 3; feature branch and planning commit first.
- Expected changes: Honor non-navigation on every completion/early-return path; public resume uses it, keeps status quiet, and shares Account's in-place state notification. Preserve static/keyless/unregistered exemptions and intentional redirects.
- Verification approach: Extend PrivateSiteAuthTest for approved/unapproved, already authenticated, configured destinations, missing keys, failure and expired-challenge retry; prove regression tests fail on baseline.
- Risks or open questions: Incorrect approval or stale markers; validate result consumers before changing completion semantics and retain distinct approval evidence.
- Canonical components/API contracts touched: private_site_auth.js; existing authenticate(options), in-place event and authentication APIs; no wire-format changes.
- Estimate details: Stage work; driver: completion branches; uncertainty: state/status compatibility.

## Stage 2 — Overlapping actions retain their intent

**Estimate: 0.10**

- Goal: Keep background completion and explicit continuations correct during overlapping calls.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Share authentication work without inheriting another caller's navigation policy; guard identity changes and preserve forced approval checks. Adjust canonical callers only where necessary.
- Verification approach: Both call orders, changed identity, forced refresh, one intended navigation, and in-place invitation/Account completion; run PrivateSiteAuthTest, AuthNavigationTest and AccountKeyPageHooksTest.
- Risks or open questions: Concurrent challenges or obsolete results; serialize incompatible work and reject stale completion before UI updates/continuations. Resolve before Stage 3.
- Canonical components/API contracts touched: Same helper; Account, protected links, access-history, invitations, browser signing and Railspike callers retain their intended outcomes.
- Estimate details: Stage work; driver: shared-flight semantics; uncertainty: forced-call interactions.

## Stage 3 — Prove uninterrupted writing and recovery

**Estimate: 0.10**

- Goal: Replace the compose reload workaround with ordinary-flow evidence.
- Dependencies: Stage 2 verified and committed.
- Expected changes: Extend compose_identity_row.mjs and a focused background-auth browser scenario using existing fixtures; no new test dependency.
- Verification approach: Delay authentication while typing/name-editing and submitting; assert document identity, text/selection/focus/scroll/open controls, exactly one accepted submission and usable session. Exercise failed auth then explicit retry, both membership states and exemption cases.
- Risks or open questions: Draft restoration can mask a reload; observe navigation count/document identity separately from text and server acceptance.
- Canonical components/API contracts touched: Existing thread/composer, Account recovery and authentication endpoints.
- Estimate details: Stage work; driver: deterministic request timing; uncertainty: fixture state isolation.

## Stage 4 — Compatibility and closeout

**Estimate: 0.08**

- Goal: Verify preserved access flows and record release limits.
- Dependencies: Stage 3 verified and committed.
- Expected changes: Only necessary assertion corrections and cycle/handoff/UI verification documentation; owns shared acceptance and study bookkeeping.
- Verification approach: Run first_post_identity, account_states, railspike_auth_navigation and relevant invitation browser checks; phone/desktop rendered initial/success/failure states across affected themes, overflow and primary-action stability. Run focused PHP checks once after final changes and document any broader-suite omissions.
- Risks or open questions: Emulation misses device behavior; label browser/device evidence precisely and retain production/physical-device follow-ups.
- Canonical components/API contracts touched: Existing access flows; implementation summary, debug log, reload handoff, UI audit checklist and study ledger.
- Estimate details: Stage work; driver: compatibility matrix; uncertainty: available browser engines.

## Execution and study checkpoint

Checkpoint: Step 3 handoff, 2026-10-28T23:52:47Z; 0.35 reaffirmed
Scope: v1 / Option A; baseline 49a9024412c7e7f6d9230fb7ed1e7b72acf4627e; R1 / P2
Rater: Codex, single exposed rater; frozen-reference verification inherited; caller/test inspection adds detail without changing scope. Pristine intake remains missing. Original stage-work estimates above are independent, not summed; planning overhead belongs to the whole-cycle projection.
Execution: Four bounded stages; each requires verification, summary update and commit before its successor. After approval, organize plans under background_sign_in/ and update navigation/index before the planning-only commit. Keep ledger bookkeeping out of that restricted commit and include it at closeout. Zero dependencies proposed; no implementation, commits or accepted outcome yet.
Authorization: Approved Step 2 received; no new requests. Await Approved Step 3.

*— Codex · 2026-10-28 UTC*
