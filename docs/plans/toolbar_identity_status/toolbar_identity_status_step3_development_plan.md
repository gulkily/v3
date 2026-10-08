# Step 3: Development Plan — Toolbar Identity Status Indicator

> **Feature plan:** [Step 1](./toolbar_identity_status_step1_solution_assessment.md) · [Step 2](./toolbar_identity_status_step2_feature_description.md) · [Step 3](./toolbar_identity_status_step3_development_plan.md) · [Step 4](./toolbar_identity_status_step4_implementation_summary.md)

## Completion Contract
- Normal user entry point: viewer loads any Forte page (board, users, or activity) through the normal browser flow.
- Observable end-to-end outcome: the toolbar's right-hand side always shows accurate logged-in (with display name) or guest status — correct at first paint, and self-correcting/live-updating thereafter.
- Failure/recovery behavior: if the local PGP keypair state disagrees with the server/cookie best-guess, or changes in another tab, the indicator corrects itself without a page reload.
- Deployment/external-system boundary: none — no new network endpoint; relies only on existing server session/cookie data and existing client-side `browser_signing.js` localStorage state.
- Release condition: indicator renders correctly for both guest and logged-in states on all three Forte views, verified manually, before the feature is considered complete.

## Key Risks
- Server/cookie best-guess and local keypair state disagree on first paint, causing a momentary visible flip.
  - Impact: low, cosmetic only.
  - Early warning/validation: exercised directly in Stage 3's verification.
  - Mitigation: accepted per Step 1/2; self-heals immediately, not treated as a defect.
- Cross-tab live update depends on the browser's native storage-change notification; if unsupported or suppressed in some context, the indicator only updates on the next page load.
  - Impact: low — indicator is still correct on every fresh load.
  - Early warning/validation: exercised directly in Stage 4's two-tab verification.
  - Mitigation: accepted graceful degradation per Step 2; no fallback needed.

## Stage 1
- Goal: Make viewer logged-in/guest status available to all three Forte pages' template data through one shared edit point.
- Dependencies: none
- Expected changes: Add default viewer-status injection to `RouteServices::renderStandalonePage()`, mirroring the existing `defaultViewerProfile()` injection already used by `RouteServices::renderPageTemplate()`, so page data gains a viewer-status value (logged-in flag + display name when known) sourced from the existing viewer-profile resolver / `identity_hint` cookie. No changes needed in the three page controllers themselves.
- Verification approach: Load board/activity/users pages while logged in and as guest; confirm each page's rendered data carries the correct status.
- Risks or open questions:
  - Impact: a bug here would make all three pages' initial guess wrong.
  - Early warning/validation: checked by switching identity before building UI on top of it.
  - Mitigation: reuse the existing, already-exercised `defaultViewerProfile()` pattern rather than new resolution logic.
- Canonical components/API contracts touched: `RouteServices::renderStandalonePage()`, `RouteServices::defaultViewerProfile()` (reused), existing viewer-profile/`identity_hint` resolver.

## Stage 2
- Goal: Render the status indicator in the toolbar's right-hand side from the Stage 1 server-seeded data.
- Dependencies: Stage 1
- Expected changes: Add a right-aligned element to the shared `paned_toolbar.php` partial showing "Guest" or the identity's display name, reusing an existing badge style (`.paned-agent-badge` or `.account-key-status-badge`) rather than introducing a new one. Adjust the toolbar row's layout (e.g. `justify-content`) so the element actually sits on the right.
- Verification approach: Load all three Forte pages as guest and as a known identity; confirm the indicator appears top-right with correct text on first paint, no flash.
- Risks or open questions: none material — additive markup/CSS reusing existing styles.
- Canonical components/API contracts touched: `templates/partials/paned_toolbar.php`, `.paned-agent-badge` / `.account-key-status-badge`.

## Stage 3
- Goal: Correct the indicator against the browser's actual local PGP keypair state (the authoritative signal) on page load.
- Dependencies: Stage 2
- Expected changes: New shared client script that, on load, checks `browser_signing.js`'s existing identity/keypair localStorage state and updates the indicator if it disagrees with the server-seeded guess. Include this script in all three pages' existing script-path lists, alongside their page-specific reader scripts.
- Verification approach: Create a mismatch (e.g. stale `identity_hint` cookie vs. cleared local keypair) and confirm the indicator corrects itself shortly after load, on all three pages.
- Risks or open questions:
  - Impact: indicator may visibly flip right after paint in the mismatch case.
  - Early warning/validation: exercised in this stage's own verification.
  - Mitigation: accepted per Step 1/2; documented, not a defect.
- Canonical components/API contracts touched: new client script, `browser_signing.js` (read-only), each page controller's script-path list.

## Stage 4
- Goal: Keep the indicator correct across open tabs without a reload when the local keypair changes in another tab.
- Dependencies: Stage 3
- Expected changes: Extend the Stage 3 script to listen for the browser's native cross-tab storage-change notification and re-run the Stage 3 correction check when the relevant local identity keys change.
- Verification approach: Open two tabs on a Forte page; clear/import a key in one tab; confirm the other tab's indicator updates within a reasonable time without a reload.
- Risks or open questions: see Key Risks (cross-tab notification support) — accepted graceful degradation, no fallback needed.
- Canonical components/API contracts touched: same client script as Stage 3.
