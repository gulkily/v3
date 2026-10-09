# QDB Cross-Theme Quote Actions — Step 3: Development Plan

> **Feature plan:** [Step 2](./qdb_cross_theme_quote_actions_step2_feature_description.md) · [Step 3](./qdb_cross_theme_quote_actions_step3_development_plan.md) · [Step 4](./qdb_cross_theme_quote_actions_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader uses any available theme on a QDB listing or numeric
  quote permalink.
- End-to-end outcome: the score is followed by natural-width vote and flag
  buttons that wrap inside the card only when space requires it.
- Required recovery: long captions and narrow viewports preserve usable,
  visible controls and existing reaction feedback.
- Deployment/external verification: after release, inspect QDB in Word97 and
  Sticker at desktop and narrow widths.
- Release condition: scoped CSS checks and QDB rendering/reaction tests pass;
  the selector affects no non-QDB control group.

## Key Risks

- **High risk: global CSS reach.** A broad rule could resize unrelated buttons.
  Validate selector scope first; target only `quote-card-header-actions`.
- **High risk: usability.** Long captions can overflow at narrow widths.
  Validate wrapping with the existing long-caption fixture; constrain the group
  to available width and preserve wrapping.
- **High risk: theme regression.** Layout rules can mask a theme's treatment.
  Limit changes to width, margin, and layout; do not set visual properties.

## Stage 1

- Goal: Make the existing QDB header controls compact and responsive in every
  theme without changing theme appearance or reaction behavior.
- Dependencies: approved Step 2; existing `quote-card-header-actions` markup
  and QDB-specific compact refinement.
- Expected changes: add a narrowly scoped shared layout baseline for the QDB
  header action group and its buttons; add focused coverage/evidence that the
  selector and QDB rendering contract remain intact; record the Step 4 result.
- Verification approach: inspect CSS selector scope; run QDB quote-card and
  reaction-browser tests; run stylesheet syntax/integrity checks; review a
  long caption pair in representative non-QDB themes at narrow width when
  release access is available.
- Risks or open questions:
  - Impact: controls may still stack or a generic button may be resized.
  - Early warning / validation: assert the shared action class in QDB output
    and review the CSS rule's exact descendant selector.
  - Mitigation: keep QDB-specific visual declarations in `theme-qdb.css` and
    limit shared declarations to layout properties.
- Canonical components/API contracts touched: `site.css`,
  `qdb_quote_actions.php`, `QuoteCardDisplayNumberTest`; no theme visual,
  template, reaction-script, or API contract changes.

Waiting for "Approved Step 3" before creating the follow-up feature branch or implementing.
