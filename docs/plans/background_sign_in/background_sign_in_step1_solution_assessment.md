> **Feature plan:** [Step 1](./background_sign_in_step1_solution_assessment.md) · [Step 2](./background_sign_in_step2_feature_description.md) · [Step 3](./background_sign_in_step3_development_plan.md) · [Step 4](./background_sign_in_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/background_sign_in; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Status: Step 1 proposed; implementation not authorized
**Cycle estimate: 0.35**

## Original Query

Please take a look at docs/plans/backlog/unexpected_page_reload_handoff.md and let's talk about a fix.

Background sign-in sounds good to me, write step 1.

## Understood Intent

Retain background sign-in on public sites while preventing it from interrupting reading or interaction. This follows the [reload handoff](../backlog/unexpected_page_reload_handoff.md) from [ui_first_post_identity](../ui_first_post_identity/ui_first_post_identity_step4_implementation_summary.md) and Account's existing in-place sign-in.

## Problem

Restoring a public-site session can reload the current page after someone starts reading, typing, editing their name, or submitting an action.

## Option A: Sign in in place — recommended

**Estimate: 0.35**

Retain eligible background session restoration, but never let it navigate or replace the current document. Reflect a genuine authentication change only where needed; otherwise use the restored session on the next navigation. Keep successful authentication distinct from membership approval.

- Pros: preserves current work and session convenience; builds on Account's existing behavior; shared static pages keep their current sign-in exemption.
- Cons: session-dependent controls and all authentication completion paths need checking, including an already authenticated identity and concurrent callers.
- Driver and uncertainty: delayed-authentication browser coverage and shared-caller behavior, rather than the size of the code change.

## Option B: Authenticate only when needed

**Estimate: 0.55**

Remove automatic public-page sign-in and establish sessions when a protected feature or explicit sign-in needs one.

- Pros: removes background authentication traffic as well as its reload.
- Cons: changes when sessions become available; requires a wider audit of session consumers and their failure/retry flows; does not match the user's preferred direction.
- Driver and uncertainty: identifying every ordinary entry that depends on an existing session.

## Recommendation and boundaries

Choose A as one releasable slice: open a public thread with a registered saved key and expired session, begin interacting, and let background authentication complete without losing the document, text, focus, scroll, menus, or pending actions. Authentication failure must preserve that work and leave existing explicit recovery usable. Preserve keyless/unregistered browsing, static first paint, Account/invitation flows and intentional members-only navigation. Do not add background publication of unused keys.

Acceptance should deliberately delay authentication while typing and editing a name, prove the session succeeds without navigation, and cover approved/unapproved identities, failures, retries and overlapping calls on desktop and phone-sized browsers. Record physical-device limits honestly. The current compose test's reload allowance should become a preservation assertion.

Version-check navigation (including back/forward restoration) and production session retention remain separate follow-ups in the handoff. Idle-triggered reloads are rejected because reading is interaction too. Dependency delta: zero proposed. No schema or record-format changes proposed.

## Study checkpoint

Checkpoint: Step 1 handoff, 2026-10-28T23:39:23Z
Scope: v1 / Option A; baseline 49a9024412c7e7f6d9230fb7ed1e7b72acf4627e; R1 / P2
Projection: 0.35 whole scoped cycle, including planning, verification, documentation and ordinary correction; alternatives are not additive or measured labor.
Rater: Codex, single exposed rater; handoff, authentication/version source and related plan inspected before rating. Pristine intake missing; no retrospective intake invented.
References: All three frozen R1 hashes verified against the ledger. Relative to toolbar identity (0.35), this is a smaller UI change with significant interruption/recovery verification.
Authorization: User selected the background direction and requested Step 1; awaiting Approved Step 1 before Step 2. Planning and study records remain uncommitted; no accepted outcome inferred.

*— Codex · 2026-10-28 UTC*
