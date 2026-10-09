# QDB Compact Quote Action Buttons — Step 2: Feature Description

> **Feature plan:** [Step 2](./qdb_compact_quote_action_buttons_step2_feature_description.md) · [Step 3](./qdb_compact_quote_action_buttons_step3_development_plan.md) · [Step 4](./qdb_compact_quote_action_buttons_step4_implementation_summary.md)

## Problem

Although QDB quote actions now sit together in every theme, inherited button
padding makes them taller than the adjacent quote ID and score. Their separate
thread and post feedback nodes also sit below the header and can leave an old
reaction message visible beside a newer one.

## User Stories

- As a QDB reader using any theme, I want vote and Flag controls to have a
  compact height comparable to the quote ID and score so the header scans as
  one line.
- As a theme user, I want compact QDB controls without changing the size of
  unrelated buttons or losing the selected theme's visual character.
- As a QDB voter, I want one current status immediately after the controls so
  I see the outcome of my latest action without stale voting messages.

## Core Requirements

- Give only QDB quote-header reaction buttons compact typography, line height,
  and padding suitable for the adjacent header metadata.
- Ensure the component-scoped metric takes precedence over the shared default
  and the Sticker reaction-button padding while preserving theme colors,
  borders, shadows, and disabled styling.
- Place one accessible shared reaction-status element directly after the QDB
  vote and Flag controls; it serves both thread votes and post flags.
- Clear a prior QDB reaction message when any new vote or flag begins, then
  show only the pending, latest success, or latest failure status.
- Retain natural width, wrapping, labels, reaction attributes, keyboard
  operation, score updates, and current feedback wording.
- Do not resize buttons outside the existing QDB quote-header action group.

## Delivery Scope

- **Work type:** application change — scoped shared CSS, QDB status markup and
  reaction binding, regression coverage, and Step 4 summary.
- **Out of scope:** site-wide button sizing, theme redesigns, QDB action-label
  changes, changes to non-QDB feedback, and reaction/scoring behavior changes.

## Completion Boundary

- **Normal entry:** a reader uses any available theme on a QDB listing or
  numeric quote permalink.
- **End-to-end outcome:** the three controls remain natural-width and wrap as
  needed, with a compact height comparable to the quote header metadata; one
  current status appears immediately after them.
- **Recovery:** long labels remain readable and operable at narrow widths; a
  failed action replaces, rather than appends to, the prior status.
- **Release condition:** scoped CSS and QDB rendering/reaction checks pass;
  the change cannot resize unrelated controls.

## Risks

- **Compact-target usability:** excessive reduction can harm touch targets or
  legibility. *Earliest validation:* inspect desktop and narrow layouts with a
  long caption. *Mitigation:* reduce only excess padding and preserve readable
  inherited typography.
- **Theme override precedence:** Sticker's rule can retain extra padding.
  *Earliest validation:* inspect CSS specificity. *Mitigation:* use a more
  specific component selector without `!important`.
- **CSS reach:** a generic selector could resize other reactions. *Earliest
  validation:* inspect the selector and a generic card. *Mitigation:* scope it
  to `quote-card-header-actions` descendants.
- **Shared feedback binding:** thread and post handlers could target different
  nodes or retain stale text. *Earliest validation:* vote, then flag, including
  a failed action. *Mitigation:* resolve the same QDB status node in both
  handlers and clear it at action start.

## Shared Component Inventory

- `qdb_quote_actions.php` owns the existing quote-header action class; reuse
  it unchanged.
- `site.css` owns the shared component layout and default button metrics;
  extend only its QDB action-group and status selectors.
- `theme-qdb.css` and other theme stylesheets retain their visual treatments;
  no per-theme change is required.
- `quote_card.php` and the QDB branch of `thread_root_card.php` render current
  separate feedback nodes; replace them with the shared QDB status placement.
- `thread_reactions.js` binds vote and flag feedback; extend it so both QDB
  action types resolve and replace one shared status without changing APIs.

## Simple User Flow

1. A reader selects any theme and opens a QDB listing or numeric permalink.
2. The quote ID, score, caption votes, `⚑ Flag`, and one status area appear in
   one compact header area.
3. The reader votes or flags normally; its current pending or final outcome
   replaces any earlier status.

## Success Criteria

- QDB quote-header buttons have a visibly compact height comparable to the
  quote ID and score in QDB, Word97, Sticker, and other available themes.
- Long caption labels remain usable and wrap within the card at narrow widths.
- No other button or reaction component changes size, appearance, or behavior.
- QDB listings and numeric permalinks render exactly one shared status after
  the controls, and a later vote or flag cannot leave a prior status visible.

Waiting for "Approved Step 2" before drafting Step 3.
