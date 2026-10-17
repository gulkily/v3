> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 2: Feature Description — Stable Messages/Invite Nav Buttons

## Direction (from Step 1 approval)
Pre-render the Messages/Invite nav buttons so they are present on first paint (Options B/C), plus a client-side fallback that reconciles the nav when the client or another tab changed (e.g. signed in/out, identity switched).

## Problem
Signed-in, approved viewers see the Messages and Invite nav buttons pop in after the page loads, which feels janky. The cause is not yet confirmed; confirming which pages/paths omit them is the first validation task.

## User Stories
- As a signed-in approved member, I want Messages and Invite in the nav on first paint so that the page doesn't shift or flicker.
- As a member who signs in or out in another tab, I want this tab's nav to correct itself so that it never shows buttons I can't use or hides ones I can.
- As a guest or unapproved visitor, I want no Messages/Invite buttons so that I'm not shown actions I can't use.

## Core Requirements
- Messages and Invite are in the initial nav markup for viewers who are eligible, on every page that shows the main nav.
- Pre-rendering must never show them to ineligible viewers, including on cached/shared pages.
- A client fallback reconciles the nav with the server's answer after load and on identity change from another tab, adding or removing only what differs (no full re-render, no shift when already correct).
- Existing behavior is preserved: unread count, messaging-disabled hiding, qdb/members-only nav variants, Invite click handling.

## Delivery Scope
- Work type: application change (no schema changes).
- Completion boundary: eligible viewer loads any page → nav is complete on first paint; changing identity in another tab → nav corrects without reload; ineligible viewer never sees the buttons. Releasable once verified in a browser across guest, approved, and cross-tab cases.

## Risks
- **Cached/static pages leak or omit viewer items** — impact: wrong buttons shown or still late. Validate first by identifying which pages are served without viewer context. Mitigation: hidden-by-default pre-rendered items revealed only on confirmed eligibility, or viewer-specific responses where already per-viewer.
- **Client and server state disagree** — impact: flicker or stale buttons. Validate with cross-tab sign-in/out test. Mitigation: server answer is authoritative; reconcile only on difference.
- **Messaging scripts depend on the link being present** — impact: unread polling fails to start/stop. Validate by checking start/stop paths against pre-rendered links. Mitigation: reuse the existing start/stop hooks instead of new ones.

## Shared Component Inventory (extend, don't fork)
- `templates/partials/nav.php` and `TemplateRenderer::navItems()` — canonical nav markup; extend.
- `GET /api/feature_flags/navigation` — existing server answer for nav/messaging eligibility; reuse as the reconcile source.
- `public/assets/feature_flags.js` — existing nav patch logic; extract/reuse for the fallback rather than duplicating.
- `private_message_availability.js`, `private_message_unread.js`, `private_message_history_sync.js` — messaging start/stop; reuse unchanged.
- `invite_navigation.js` — Invite click handling; unchanged.
- `theme-nav-icons.css` / `site.css` — nav styling; ensure hidden/revealed states keep icon parity.

## Simple User Flow
1. Eligible viewer opens any page.
2. Nav arrives with Messages and Invite already in place.
3. After load, the client checks eligibility; if unchanged, nothing visibly happens.
4. If identity changed elsewhere, the nav adds/removes the buttons to match.

## Success Criteria
- No Messages/Invite insertion or layout shift after first paint for eligible viewers on a normal load (verified in a browser, all themes with nav icons).
- Guests/unapproved viewers never see the buttons, including on cached pages.
- Cross-tab sign-in/out updates the nav within a few seconds without reload.
- Existing messaging and invite tests still pass.

**Approval:** Reply `Approved Step 2` to proceed to Step 3.
