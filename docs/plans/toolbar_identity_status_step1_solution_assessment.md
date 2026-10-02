# Step 1: Solution Assessment — Toolbar Identity Status Indicator

> **Feature plan:** [Step 1](./toolbar_identity_status_step1_solution_assessment.md) · [Step 2](./toolbar_identity_status_step2_feature_description.md) · [Step 3](./toolbar_identity_status_step3_development_plan.md) · [Step 4](./toolbar_identity_status_step4_implementation_summary.md)

## Original Query
As a user of the Forte interface, I want to know what my identity status is. In the right portion of the toolbar, I'd like to add an indicator for my logged in or guest status, please.

## Problem
The Forte toolbar gives no visual cue of whether the current viewer is logged in (approved identity) or a guest, and the toolbar's right-hand side is currently unused.

## Option A: Server-resolved indicator (render at page load)
Controller passes the already-resolved viewer identity/approval state into the toolbar template; indicator renders server-side with the rest of the page.
- Pros: No extra request; always consistent with server auth state; simplest mental model; toolbar partial is shared across all three Forte views so one change propagates everywhere.
- Cons: Requires passing one additional prop through each of the three page controllers into the shared toolbar partial.

## Option B: Client-side fetch of auth status
Toolbar renders a placeholder, then JS calls the existing auth-status endpoint on load to fill in logged-in/guest state.
- Pros: No controller changes needed.
- Cons: Extra network round-trip; brief flash of "unknown" state; more moving parts (loading/error states) for a simple display.

## Option C: Read the identity-hint cookie client-side
JS reads the existing non-httponly cookie that already stores username-or-guest and renders the indicator from that alone.
- Pros: Zero backend changes, instant render, no fetch.
- Cons: The cookie is a display hint, not the authoritative session check, so it can drift from real auth state (e.g. stale cookie after server-side session expiry).

## Option D: Hybrid — server-rendered first paint, localStorage as authoritative correction
Toolbar renders immediately using the server-resolved state (Option A) or the `identity_hint` cookie (Option C) as a best-guess for instant, flash-free paint; on load, client JS re-checks the browser's PGP keypair in localStorage (the same store `browser_signing.js` already treats as the real source of truth for "am I logged in," via its username + public/private key entries) and corrects the indicator if it disagrees. The browser's native cross-tab storage-change notification also lets the indicator update live if the keypair is cleared or added in another tab, without a page reload.
- Pros: No flash of unknown state (seamless like A/C); final displayed state matches what actually governs signing/posting behavior, since the PGP keypair in localStorage — not the server session or the cookie hint — is what determines whether the browser can act as that identity; self-healing if cookie/session and localStorage ever drift; updates live across tabs for free, since the browser already notifies other tabs of localStorage changes.
- Cons: Most moving parts of the four options — needs a server-side prop, a client-side correction step, and a cross-tab listener; indicator can briefly flip on load or mid-session in the rare case the states disagree (accepted).

## Recommendation
**Option D.** Guest/logged-in status is ultimately a property of whether the browser holds a usable PGP keypair, which lives in localStorage, not in the server session alone — so only a solution that reads localStorage can be fully correct. Seeding the first paint from the server/cookie keeps it flash-free, and the localStorage check keeps it accurate. This is a complete, independently usable end-to-end slice: a user loads any Forte page and immediately sees a correct, self-correcting status indicator through the normal UI flow — no other feature is required to make it usable.
