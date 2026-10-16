> **Feature plan:** [Step 1](./invite_zero_click_step1_solution_assessment.md) · [Step 2](./invite_zero_click_step2_feature_description.md) · [Step 3](./invite_zero_click_step3_development_plan.md) · [Step 4](./invite_zero_click_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Let's work on this part of the todo.txt. Let's write Step 1 of Railspike, but also discuss it:

The invite path needs to be fixed:
- The target link feature doesn't seem to work at all.
- If there's a target link, we need to end up at the link with zero clicks, even if it's a closed resource.
- We can ask for the username later.
- At first, we can just make an approved placeholder key or something.

## Problem statement

Opening a valid targeted invitation should automatically establish authorized access and display its destination without redemption clicks or username prompts.

## Current evidence

Redemption currently requires a click and fresh-key setup requests a username; the destination crosses a reload through browser storage and can lose priority to another authentication return destination. These are observed code paths, not a reproduced diagnosis of the reported target failure.

## Option A — Automatically redeem into a persistent browser identity

Reuse the browser's existing identity, or create a real browser-owned key with a temporary name, automatically redeem the invitation, authenticate, and navigate to its target; offer naming after arrival without replacing the key.

- Pros: Fits existing signed invitations and member approval; provides immediate access and a durable identity; avoids a separate guest-access system.
- Cons: Opening the link consumes the invitation and may create an approved account; later naming must preserve identity and approval, and its feasibility needs validation.

## Option B — Temporary invitation-backed access

Give the browser a temporary access session that opens the target immediately, then create or connect a permanent identity later.

- Pros: Defers account creation and naming; can potentially limit initial access to the target.
- Cons: Adds a second authorization lifecycle, with expiry, recovery, and conversion rules; less reuse of the current key-based membership model.

## Recommendation and discussion

Prefer **Option A**, using a real key with a temporary name rather than a shared or disposable key. Keep the vertical slice complete: open invitation → establish/reuse identity → redeem and verify access → display target → allow later naming without losing access. Diagnose destination loss across issuance, redemption, and authentication together.

- “Zero clicks” means no interaction after opening a valid invite in a supported browser; invalid, expired, revoked, or consumed invitations and unavailable browser storage need clear recovery.
- Preserve existing identities and approval rules. A target must not bypass unrelated resource permissions; clarify whether “closed” means members-only access or additional resource-specific restrictions.
- Let the invite target take priority over generic return destinations, retain its valid query/fragment, and restrict navigation to supported internal destinations. Missing/deleted targets need a useful fallback after redemption.
- Confirm that opening a link intentionally consumes its single-use invitation, including when opened by a JavaScript-capable preview tool. Interrupted redemption should resume safely for the same recipient.
- Later naming should be nonblocking; decide whether to offer it on the destination or at the next identity-requiring action. Validate name changes before committing to permanent placeholder identities.
