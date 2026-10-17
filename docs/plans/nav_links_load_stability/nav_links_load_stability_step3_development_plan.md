> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 3: Development Plan — Stable Messages/Invite Nav Buttons (Revised)

## Completion Contract
- Normal entry: an eligible viewer loads any page with the main nav.
- End-to-end outcome: Messages/Invite never move; unread > 0 shows as an outline plus ARIA, caption stays `Messages`.
- Required recovery: if the unread check fails, no outline, ARIA says status unavailable, size unchanged.
- Deployment/external verification: browser check in each theme with nav icons.
- Release condition: all stages verified; browser tests updated and passing; no schema changes.

## Key Risks
- Theme styling may hide or distort the outline; validate in Stage 2. Mitigation: layout-neutral outline, per-theme tweaks only where needed.
- Scripts and tests depend on the removed count text; validate in Stage 1. Mitigation: drive state via attributes/ARIA.

## Stage 1
- Goal: remove visible count/placeholder; keep state in attributes and ARIA.
- Dependencies: none.
- Expected changes: nav partial drops the visible count span and `…`; unread script and flag-toggle error path stop writing visible text and instead set unread state attributes and accessible name/description; browser tests updated.
- Verification approach: browser tests for unread UI, live navigation, seen windows/recovery; measure nav item widths before/after load.
- Risks or open questions:
  - Impact: errors where code expects removed elements.
  - Early warning / validation: console errors in unread tests.
  - Mitigation: null-safe updates.
- Canonical components/API contracts touched: `nav.php`, `private_message_unread.js`, `feature_flags.js`, listed browser tests.

## Stage 2
- Goal: outline highlight when unread > 0.
- Dependencies: Stage 1.
- Expected changes: CSS rule keyed on the unread state attribute using a layout-neutral outline; theme adjustments if needed.
- Verification approach: visual check per theme; bounding boxes unchanged with/without unread.
- Risks or open questions:
  - Impact: weak contrast or bevel clash.
  - Early warning / validation: screenshot per theme.
  - Mitigation: per-theme override via existing nav-icons theme scoping.
- Canonical components/API contracts touched: `site.css`, `theme-nav-icons.css`.

## Stage 3
- Goal: final verification and regression pass.
- Dependencies: Stages 1–2.
- Expected changes: fix-ups and added assertions (caption constant, no shift, outline + ARIA).
- Verification approach: full browser and PHP test runs; manual guest/approved check.
- Risks or open questions:
  - Impact: guest or disabled-messaging regressions.
  - Early warning / validation: existing disabled/unavailable tests.
  - Mitigation: reuse existing availability hooks.
- Canonical components/API contracts touched: tests; no new contracts.

**Approval:** Reply `Approved Step 3` to begin Stage 1.
