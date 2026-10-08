# Step 2: Feature Description — Toolbar Identity Status Indicator

> **Feature plan:** [Step 1](./toolbar_identity_status_step1_solution_assessment.md) · [Step 2](./toolbar_identity_status_step2_feature_description.md) · [Step 3](./toolbar_identity_status_step3_development_plan.md) · [Step 4](./toolbar_identity_status_step4_implementation_summary.md)

## Problem
Viewers of the Forte interface have no visual cue telling them whether they're currently recognized as a logged-in identity or browsing as a guest.

## User Stories
- As a Forte viewer, I want to see my logged-in/guest status in the toolbar so that I don't have to guess whether my posts/votes will be attributed to my identity.
- As a Forte viewer who clears or imports a PGP key in another tab, I want the indicator to update automatically so that it never shows a stale status.

## Core Requirements
- Toolbar's right-hand section shows a status indicator reading logged-in (with identity) or guest at all times.
- Indicator renders with the initial page paint using server/cookie-resolved state — no flash of "unknown."
- On load, client JS corrects the indicator against the browser's local PGP keypair state, which is the authoritative signal for whether the browser can act as that identity.
- Indicator updates live if the local keypair changes in another browser tab, without a page reload.
- Indicator appears consistently across all three Forte views (board, users, activity), since they share one toolbar partial.

## Shared Component Inventory
- `paned_toolbar.php` — the single shared toolbar partial already rendered by all three Forte page templates; this is where the indicator is added, not a new per-page toolbar.
- `browser_signing.js` — already the sole client-side source of truth for local identity/keypair state; the indicator reads this rather than introducing a second identity check.
- `.paned-agent-badge` / `.account-key-status-badge` — existing pill/badge styles already used elsewhere in the app for similar small inline status text; the indicator should reuse one of these rather than introducing a new badge style.
- No new component is needed; this extends existing surfaces only.

## Simple User Flow
1. Viewer opens any Forte page (board, users, or activity).
2. Toolbar paints immediately showing a best-guess status (logged-in or guest) from server/cookie state.
3. Client JS checks the local PGP keypair and corrects the indicator if needed.
4. If the viewer changes their local identity in another tab (e.g. imports or clears a key), the indicator updates within a reasonable time, with no reload required.

## Risks & Mitigations
- **Risk**: Server/cookie best-guess and local keypair state disagree on first paint, causing a visible flip. **Impact**: minor, momentary visual correction. **Mitigation**: accepted behavior per Step 1; disagreement is rare and self-heals immediately.
- **Risk**: Cross-tab live update relies on a browser storage-change notification; if unsupported or suppressed in some context, the indicator would only update on next page load. **Impact**: low — indicator still correct on every fresh page load. **Mitigation**: no fallback needed; graceful degradation to reload-to-refresh is acceptable.

## Success Criteria
- The toolbar's right-hand section shows the correct logged-in/guest status within a reasonable time of every page load, on all three Forte views.
- Clearing or adding a local PGP identity in one tab updates the indicator in all other open Forte tabs without a reload.
- No added network request is needed solely to render the indicator (first paint uses already-available server/cookie state).
