# QDB Compact Quote Action Buttons — Step 3: Development Plan

> **Feature plan:** [Step 2](./qdb_compact_quote_action_buttons_step2_feature_description.md) · [Step 3](./qdb_compact_quote_action_buttons_step3_development_plan.md) · [Step 4](./qdb_compact_quote_action_buttons_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader opens a QDB listing or numeric permalink in any theme.
- End-to-end outcome: compact header controls are followed by one current,
  accessible status for either a vote or flag.
- Required recovery: a new action clears an earlier message; a failure replaces
  it with the current error while controls remain usable.
- Deployment/external verification: after release, inspect Word97 and Sticker
  at desktop and narrow widths, including a vote followed by a flag.
- Release condition: focused QDB rendering, stylesheet, and reaction-browser
  tests pass; no non-QDB feedback contract changes.

## Key Risks

- **High risk: compact-target usability.** Controls could become hard to read
  or operate. Validate long captions at narrow width; reduce excess padding
  only and preserve inherited readable type.
- **High risk: shared-feedback integrity.** Both handlers must use one node.
  Validate vote-to-flag and failure sequences; resolve the shared QDB target in
  both handlers and clear it before action preparation.
- **High risk: CSS reach.** A selector could affect unrelated controls. Check
  its exact component scope; limit it to quote-header descendants.

## Stage 1

- Goal: Deliver compact QDB header actions and one live status that serves both
  vote and flag actions.
- Dependencies: approved Step 2; existing quote-header action component and
  reaction handler contracts.
- Expected changes: adjust component-scoped button metrics; place one status
  immediately after the controls on listing and permalink cards; update
  reaction feedback lookup/clearing so a later QDB action replaces the prior
  message while non-QDB feedback remains unchanged.
- Verification approach: PHP/JS syntax checks; focused QDB render tests;
  existing reaction-browser tests for pending, success, and failure behavior.
- Risks or open questions:
  - Impact: one action type might lose feedback or stale text might persist.
  - Early warning / validation: vote then flag in the browser fixture and
    inspect the single feedback node.
  - Mitigation: use the QDB-only status target first, retaining legacy targets
    for non-QDB cards.
- Canonical components/API contracts touched: `qdb_quote_actions.php`,
  `quote_card.php`, QDB `thread_root_card.php`, `site.css`,
  `thread_reactions.js`; existing signed reaction API is reused unchanged.

## Stage 2

- Goal: Protect the compact cross-theme layout and one-status replacement
  contract with focused regression coverage.
- Dependencies: Stage 1's shared target and compact styling.
- Expected changes: add rendering/style assertions for one status after the
  controls and reaction-browser coverage that verifies a new QDB action clears
  and replaces an older status; update the Step 4 summary.
- Verification approach: run stylesheet, QDB quote-card, and reaction-browser
  focused suites; inspect changed-file scope and CSS selectors.
- Risks or open questions:
  - Impact: test fixtures may still model separate legacy feedback nodes.
  - Early warning / validation: run both QDB-specific and general reaction
    fixtures.
  - Mitigation: preserve fallback handling for all existing non-QDB markup.
- Canonical components/API contracts touched: `ThemeRegistryTest`,
  `QuoteCardDisplayNumberTest`, `BrowserSigningNormalizationTest`; no API or
  persistence contract changes.

Waiting for "Approved Step 3" before creating a feature branch or implementing.
