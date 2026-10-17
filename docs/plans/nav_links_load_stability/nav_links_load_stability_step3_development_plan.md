> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 3: Development Plan — Stable Messages/Invite Nav Buttons

## Completion Contract
- Normal entry: an eligible (approved, signed-in) viewer opens any page with the main nav.
- End-to-end outcome: Messages and Invite are in the first-paint nav with no post-load insertion or shift; guests/unapproved never see them; identity changes in another tab correct the nav without reload.
- Required recovery: if the reconcile request fails, the pre-rendered state stays and the existing "unavailable" indicators apply; reload restores correctness.
- Deployment/external verification: browser check on a deployed-like build (static/cached page path and per-viewer path), multiple themes with nav icons.
- Release condition: all stages verified, existing messaging/invite tests pass, no schema changes.

## Key Risks
- **High risk:** cached/static pages could show the buttons to ineligible viewers. Validate in Stage 1 by mapping which pages are served without viewer context. Mitigation: items pre-rendered hidden and revealed only on confirmed eligibility.
- Client/server disagreement causes flicker. Validate in Stage 4 with cross-tab tests. Mitigation: server answer is authoritative; change the DOM only on difference.
- Messaging scripts expect the Messages link when starting. Validate in Stage 3. Mitigation: reuse the existing start/stop hooks.

## Stage 1
- Goal: confirm why the buttons arrive late and which pages are affected.
- Dependencies: none.
- Expected changes: none to application code; add a short findings note to the Step 4 summary (page types served with vs. without viewer context, and the client path that adds the items).
- Verification approach: load a static/cached page and a per-viewer page as guest and approved viewer; record nav HTML at first response vs. after load.
- Risks or open questions:
  - Impact: wrong diagnosis leads to wrong fix.
  - Early warning / validation: first-response HTML lacks the items while post-load DOM has them.
  - Mitigation: if the cause differs from Step 2 assumptions, return to Step 2.
- Canonical components/API contracts touched: none (read-only).

## Stage 2
- Goal: pre-render Messages/Invite in the initial nav for eligible viewers.
- Dependencies: Stage 1.
- Expected changes: extend nav item building and partial so eligible-viewer items are present in first HTML; on pages served without viewer context, emit them hidden-by-default; keep qdb and members-only variants unchanged.
- Verification approach: unit tests for nav item output per viewer type; manual first-response HTML check.
- Risks or open questions:
  - Impact: hidden items could flash visible or leak to guests.
  - Early warning / validation: guest first-response HTML contains no visible Messages/Invite.
  - Mitigation: hidden attribute plus CSS guard; reveal only after server confirmation.
- Canonical components/API contracts touched: `templates/partials/nav.php`, `TemplateRenderer::navItems()`.

## Stage 3
- Goal: keep messaging and invite behavior working with pre-rendered links.
- Dependencies: Stage 2.
- Expected changes: ensure unread polling and history sync start/stop against the pre-rendered Messages link; Invite click handling unchanged.
- Verification approach: existing messaging/invite tests; manual unread count appears with no layout shift.
- Risks or open questions:
  - Impact: unread count never starts or duplicates.
  - Early warning / validation: single polling instance per page.
  - Mitigation: reuse existing start/stop hooks; no new ones.
- Canonical components/API contracts touched: `private_message_unread.js`, `private_message_availability.js`, `private_message_history_sync.js`, `invite_navigation.js`.

## Stage 4
- Goal: client fallback that reconciles nav on load and on cross-tab identity change.
- Dependencies: Stage 2, 3.
- Expected changes: extract the nav-reconcile logic from `feature_flags.js` into a shared script loaded on all nav pages; call `/api/feature_flags/navigation` after load and on `storage` events for identity keys; add/remove only differing items.
- Verification approach: browser tests: sign in/out in another tab; identity switch; failed request; no DOM change when already correct.
- Risks or open questions:
  - Impact: extra request per page load; reconcile loops.
  - Early warning / validation: one request per load/change; no repeated mutations.
  - Mitigation: debounce, generation guard (existing pattern), skip when state matches.
- Canonical components/API contracts touched: `/api/feature_flags/navigation`, `feature_flags.js`, layout script includes.

## Stage 5
- Goal: final verification across themes and regression pass.
- Dependencies: Stages 2–4.
- Expected changes: fix-ups only; tests added for guest, approved, cached-page, and cross-tab cases.
- Verification approach: full test suite; manual check in themes with nav icons; confirm no new layout shift.
- Risks or open questions:
  - Impact: icon/theme CSS mismatch for revealed items.
  - Early warning / validation: visual check per theme.
  - Mitigation: reuse existing `.nav-link` icon rules.
- Canonical components/API contracts touched: `theme-nav-icons.css`, `site.css`.

**Approval:** Reply `Approved Step 3` to start Step 4 (feature branch, planning commit, then stages).
