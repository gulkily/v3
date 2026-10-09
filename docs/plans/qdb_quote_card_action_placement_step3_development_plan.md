# QDB Quote Card Action Placement — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_quote_card_action_placement_step1_solution_assessment.md) · [Step 2](./qdb_quote_card_action_placement_step2_feature_description.md) · [Step 3](./qdb_quote_card_action_placement_step3_development_plan.md) · [Step 4](./qdb_quote_card_action_placement_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader visits a QDB collection or numeric quote permalink.
- End-to-end outcome: the quote header shows score, caption vote controls, and
  `⚑ Flag` before the quote body; established reactions still update/recover.
- Required recovery: failed reactions restore a usable control and surface
  existing feedback without a false score or flag state.
- Deployment/external verification: after release, check one collection and
  one numeric permalink using a long caption pair at a narrow viewport.
- Release condition: focused rendering/reaction tests pass; non-QDB output and
  all reaction contracts remain unchanged.

## Key Risks

- **High risk: usability.** A compact header may wrap or hide feedback.
  Validate a longest caption pair early; retain responsive layout and feedback
  outside the header.
- **High risk: surface consistency.** Listings and permalinks could diverge.
  Render both from one shared action component and assert both outputs.
- **High risk: reaction integrity.** A markup change could omit an existing
  data attribute or state. Exercise vote and flag success/failure fixtures and
  preserve the current signed-reaction contracts.

## Stage 1

- Goal: Move QDB listing quote controls into a reusable header action group.
- Dependencies: approved Steps 1–2; current caption-pair and reaction state.
- Expected changes: add a shared QDB action presentation; render it after the
  listing score; present the flag control as `⚑ Flag`; keep feedback external
  to the header; adjust QDB-only layout styling as needed.
- Verification approach: PHP syntax checks; render a QDB listing with positive,
  negative, and long-caption fixtures; run quote-card focused tests.
- Risks or open questions:
  - Impact: header action row could wrap poorly.
  - Early warning / validation: inspect rendered long-caption markup and
    narrow-layout behavior.
  - Mitigation: preserve the existing flexible button-row semantics.
- Canonical components/API contracts touched: `quote_card.php`, new shared QDB
  action component, `theme-qdb.css`; unchanged `apply-thread-tag` and
  `apply-post-tag` contracts.

## Stage 2

- Goal: Give numeric QDB quote permalinks the identical header action group.
- Dependencies: Stage 1's shared component and listing verification.
- Expected changes: replace the QDB-only permalink action markup with the
  shared presentation while retaining its viewer state and separate feedback.
- Verification approach: render a numeric permalink, assert score-before-
  controls ordering and `⚑ Flag`, then run quote-card and reaction-browser
  focused tests.
- Risks or open questions:
  - Impact: permalink-specific post/thread identifiers could be wired
    incorrectly.
  - Early warning / validation: vote and flag fixture assertions target both
    action types.
  - Mitigation: pass the existing permalink state and IDs unchanged into the
    shared component.
- Canonical components/API contracts touched: `thread_root_card.php`, shared
  QDB action component, `thread_reactions.js` selector/data-attribute contract
  (reused unchanged).

## Stage 3

- Goal: Finish reader guidance and regression protection for the released QDB
  interaction.
- Dependencies: Stages 1–2 and resolved header/feedback layout behavior.
- Expected changes: update QDB welcome copy; add focused rendering assertions
  for every QDB surface and unchanged non-QDB output; record the results in
  the Step 4 summary.
- Verification approach: run PHP syntax checks, `QuoteCardDisplayNumberTest`,
  `BrowserSigningNormalizationTest`, and the relevant QDB rendering suite;
  inspect the working tree for authorized changes only.
- Risks or open questions:
  - Impact: visible instructions may describe obsolete controls.
  - Early warning / validation: assert the new Flag wording in rendered
    welcome output.
  - Mitigation: update guidance in the same stage as presentation tests.
- Canonical components/API contracts touched: `qdb_welcome.php`,
  `QuoteCardDisplayNumberTest`, `BrowserSigningNormalizationTest`; no API,
  catalog, scoring, or moderation contract changes.

Waiting for "Approved Step 3" before creating a feature branch or implementing.
