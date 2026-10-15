> **Feature plan:** [Step 1](./account_key_page_redesign_step1_solution_assessment.md) · [Step 2](./account_key_page_redesign_step2_feature_description.md) · [Step 3](./account_key_page_redesign_step3_development_plan.md) · [Step 4](./account_key_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- **Entry:** `/account/key/` opened directly or from the nav, lobby, resume, or backup-reminder links.
- **Outcome:** state and primary action are visible first; backup/restore are prominent; technical content is grouped by intent with private-key and destructive actions set apart; every prior capability behaves identically.
- **Recovery:** clear-with-undo, restore from backup, and status/error messages unchanged.
- **Deployment/external:** none; no server, storage, or signing changes. Visual check in all themes at desktop and 375px.
- **Release:** existing account-key tests and `tests/browser/key_backup_ui.mjs` pass; no horizontal scroll at 375px.

## Key Risks
- **High risk:** moved controls lose script hooks (`data-role`, `data-action`, `data-backup-*`, IDs). Early validation: Stage 1 selector inventory; rerun tests every stage. Mitigation: keep every hook; only wrap and reorder.
- **High risk:** private key shown or cleared more easily. Early validation: Stage 4 review. Mitigation: reveal is a native disclosure, collapsed by default; clear stays behind undo.
- Theme/contrast regressions. Early validation: theme check at Stage 2. Mitigation: theme tokens only, styles scoped to `[data-account-key-root]`.
- Technical content buried. Early validation: Stage 5 review. Mitigation: one labeled section, grouped headings.

## Stage 1
- Goal: lock the hook contract before moving anything.
- Dependencies: none.
- Expected changes: add a markup test asserting every hook the script and tests use is present on the rendered page; no visual change.
- Verification approach: new test passes against the current page; existing account-key tests pass.
- Risks or open questions:
  - Impact: missed hook goes unnoticed later.
  - Early warning / validation: compare against selectors in `browser_signing.js` and `key_backup_ui.mjs`.
  - Mitigation: derive the list by grep, not memory.
- Canonical components/API contracts touched: `templates/pages/account_key.php` (read only), tests.

## Stage 2
- Goal: status-first header: title with one-line purpose, status card with state, username, profile link, and primary action.
- Dependencies: Stage 1.
- Expected changes: restructure the top block in the template; add page-scoped type scale and spacing in `identity.css`.
- Verification approach: Stage 1 test; render in each theme at desktop and 375px; keyboard focus order.
- Risks or open questions:
  - Impact: status text mirroring breaks.
  - Early warning / validation: set up and clear a key in a browser; status text updates.
  - Mitigation: keep `#simple-status` and status data roles.
- Canonical components/API contracts touched: `account_key.php`, `identity.css`, `feedback.php`.

## Stage 3
- Goal: make backup and restore a prominent, plainly explained section outside the "advanced" drawer.
- Dependencies: Stage 2.
- Expected changes: promote the `#key-backup` block to a visible section; keep tabs, copy, and warnings; adjust tab styles.
- Verification approach: `tests/browser/key_backup_ui.mjs` for text and PNG; tab keyboard behavior.
- Risks or open questions:
  - Impact: reminder link anchor `#key-backup` stops opening/focusing the section.
  - Early warning / validation: follow the reminder link.
  - Mitigation: keep the ID and expose it as the section anchor.
- Canonical components/API contracts touched: `account_key.php`, `identity.css`, `key_backup_reminder.php` (verify only).

## Stage 4
- Goal: group technical content by intent: public key, private key, storage and identity, destructive actions.
- Dependencies: Stage 3.
- Expected changes: reorder existing controls into labeled groups; wrap the private-key viewer and copy in a collapsed native disclosure with a plain warning; set clear actions apart.
- Verification approach: copy/load/restore/clear/undo manually and via tests; confirm private key is collapsed by default and viewer hooks still update.
- Risks or open questions:
  - Impact: script cannot find the viewer inside a collapsed container.
  - Early warning / validation: generate a key and open the disclosure.
  - Mitigation: disclosure only toggles visibility; the node stays in the DOM.
- Canonical components/API contracts touched: `account_key.php`, `identity.css`.

## Stage 5
- Goal: transparency labels: what lives in this browser versus on the server, and link-identity form and guest-publication setting in context.
- Dependencies: Stage 4.
- Expected changes: short explanatory text and headings only; no behavior change.
- Verification approach: copy review for accuracy against actual storage keys and cookie; tests pass.
- Risks or open questions:
  - Impact: inaccurate claim about storage.
  - Early warning / validation: check statements against script storage keys.
  - Mitigation: state only verified facts.
- Canonical components/API contracts touched: `account_key.php`.

## Stage 6
- Goal: polish and regression pass.
- Dependencies: Stages 1–5.
- Expected changes: contrast, focus rings, spacing fixes found in review; remove dead styles.
- Verification approach: full theme matrix at desktop and 375px; keyboard-only walkthrough; run PHP tests and the browser test; record results in the Step 4 summary.
- Risks or open questions:
  - Impact: late theme fixes touch shared styles.
  - Early warning / validation: diff `identity.css` for non-`.account-key` selectors.
  - Mitigation: keep changes page-scoped.
- Canonical components/API contracts touched: `identity.css`, `account_key.php`.
