> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 1: Solution Assessment — Stable Messages/Invite Nav Buttons

## Original Query
As a user, noticed that the Messages and Invite nav buttons don't appear right on page load, but get added to the page after load, which feels janky. Please write Step 1 to correct this.

## Understood Intent
Signed-in, approved viewers should see the Messages and Invite nav buttons in the first paint, with no visible pop-in or layout shift as the page finishes loading.

## Problem
The Messages and Invite nav items are viewer-dependent, and they currently show up after initial render instead of with the rest of the nav.

## Findings so far (to be confirmed in Step 2)
- Nav items are built server-side in `TemplateRenderer::navItems()`; Messages/Invite are included only when the request resolves an approved, authenticated viewer.
- Viewer resolution depends on the session/`identity_hint` cookie. When a page is served without that viewer (e.g. cached/static HTML, or a session not yet established), the two items are absent from the HTML.
- The exact client path that adds them post-load has not yet been pinpointed; `feature_flags.js` only patches Messages after a flag toggle. Confirming the trigger is the first task of Step 2.

## Option A: Reserve space, then fill in
Keep the current late addition but make it visually seamless: render placeholder slots (or fixed-width space) for Messages/Invite whenever a viewer might be signed in, and fill them when known. The nav no longer shifts, but the items still arrive late.
- Pros: smallest change; works regardless of where the viewer is learned
- Cons: still a visible fill-in; placeholders can appear for viewers who never get the items

## Option B: Render the items in the first HTML response
Make the server output include the viewer-specific items on initial render for every page, including pages currently served without viewer context, so nothing is added afterward.
- Pros: fixes the cause; no flicker, no JS dependency for the nav
- Cons: may require per-viewer responses where pages are currently shared/cached; needs a cache-safety check

## Option C: Remember the viewer client-side and render early
Have a small inline script in `<head>`/top of `<body>` read the known signed-in state (e.g. the existing local identity) and reveal pre-rendered, hidden Messages/Invite items before first paint, then reconcile with the server's answer.
- Pros: works with cached/static pages; no server cost; no shift
- Cons: client state can disagree with server state (reconciliation needed); adds inline script to the critical path

## Recommendation
- Do Step 2 diagnosis first to confirm which pages lack the items and why.
- Prefer **Option B** where pages are already per-viewer; if static/cached pages are the source, use **Option C**, with **Option A**'s reserved space as the fallback while reconciling.
- Vertical-slice viable: one user-visible outcome (stable nav on load) end to end, verifiable in a browser, with no schema changes.

**Approval:** Reply `Approved Step 1` to proceed to Step 2 (or ask for revisions/combinations).

## Revised direction (after approval)
Pre-render the items (B/C) with a client fallback that reconciles on client/cross-tab changes. See [Step 2](./nav_links_load_stability_step2_feature_description.md).
