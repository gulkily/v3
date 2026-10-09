# QDB Cross-Theme Quote Actions — Step 2: Feature Description

> **Feature plan:** [Step 2](./qdb_cross_theme_quote_actions_step2_feature_description.md) · [Step 3](./qdb_cross_theme_quote_actions_step3_development_plan.md) · [Step 4](./qdb_cross_theme_quote_actions_step4_implementation_summary.md)

## Problem

Outside the QDB theme, the shared QDB quote controls inherit the global
full-width button rule and stack vertically, making the score-header layout
unnecessarily tall.

## User Stories

- As a QDB reader using any available theme, I want quote vote and flag
  controls to sit compactly beside the score so the header remains scannable.
- As a theme user, I want each theme to retain its own button visual treatment
  so improving QDB controls does not flatten the selected theme's identity.

## Core Requirements

- Apply a shared baseline layout to QDB's existing quote-header action group
  so its buttons take natural width and have no stacked-button top margin.
- Let controls wrap within the available width on narrow screens without
  overflowing the quote card.
- Preserve all theme-specific color, border, typography, hover, and disabled
  styling, including QDB's existing compact refinement.
- Do not alter QDB button order, labels, reaction attributes, scoring, or any
  non-QDB card control.

## Delivery Scope

- **Work type:** application change — shared presentation CSS, focused
  regression coverage, and Step 4 summary.
- **Out of scope:** theme redesigns, new per-theme button styles, template or
  reaction behavior changes, and non-QDB component layout changes.

## Completion Boundary

- **Normal entry:** a reader changes to an available non-QDB theme and views a
  QDB listing or numeric quote permalink.
- **End-to-end outcome:** the score is followed by natural-width vote and flag
  controls that wrap gracefully, while the active theme remains visually
  recognizable.
- **Recovery:** on a narrow viewport, controls wrap without horizontal
  overflow or loss of reaction feedback.
- **Release condition:** shared CSS verification and QDB rendering/reaction
  tests pass; representative non-QDB theme output retains its theme rules.

## Risks

- **Global CSS reach:** a broad selector could affect unrelated buttons.
  *Earliest validation:* inspect selector scope and render a generic card.
  *Mitigation:* target only the existing QDB quote-header action class.
- **Narrow-screen wrapping:** long captions could overflow or become cramped.
  *Earliest validation:* inspect a long caption pair at narrow width.
  *Mitigation:* retain wrapping and constrain the group to card width.
- **Theme regression:** a base rule may override intentional theme visuals.
  *Earliest validation:* compare representative themed buttons. *Mitigation:*
  change only layout properties, leaving visual properties untouched.

## Shared Component Inventory

- `qdb_quote_actions.php` is the canonical QDB vote/flag group; extend its
  existing `quote-card-header-actions` presentation contract.
- `site.css` supplies global button sizing; add the narrowly scoped baseline
  there so all themes receive it.
- `theme-qdb.css` remains the QDB-specific compact refinement; other theme
  stylesheets retain their own visual rules unchanged.
- `thread_reactions.js` and reaction APIs are unaffected and reused unchanged.

## Simple User Flow

1. A reader selects Word97, Sticker, Vapor, or another non-QDB theme.
2. They open a QDB listing or numeric quote permalink.
3. The score and three natural-width controls appear together, wrapping only
   when space requires it; voting and flagging work as before.

## Success Criteria

- QDB vote and flag buttons no longer render full-width or vertically stacked
  in any available theme at ordinary viewport widths.
- Long labels wrap inside the quote card without horizontal overflow.
- QDB-theme compact presentation and representative non-QDB theme styling are
  preserved.
- QDB reaction markup and behavior, and all non-QDB card controls, are
  unchanged.

Waiting for "Approved Step 2" before drafting Step 3.
