# QDB Score Display Emphasis — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_score_display_emphasis_step1_solution_assessment.md) · [Step 2](./qdb_score_display_emphasis_step2_feature_description.md) · [Step 3](./qdb_score_display_emphasis_step3_development_plan.md) · [Step 4](./qdb_score_display_emphasis_step4_implementation_summary.md)

## Stage 1 - Render score-only emphasis

- Changes:
  - Preserved the QDB `(score/votes)` score-node contract on listing and
    permalink cards.
  - Added dedicated score-value and vote-count presentation targets on both
    surfaces.
  - Scoped bold and sign-color styling to the score value only.
- Verification:
  - `php -l templates/partials/quote_card.php` and `php -l
    templates/partials/thread_root_card.php` passed.
  - `php tests/run.php QuoteCardDisplayNumberTest::testQdbInstanceShowsTheImportedQuoteNumberNotTheInternalPostId QuoteCardDisplayNumberTest::testNonQdbInstanceStillShowsTheFullPostIdUnchanged` passed (2/2).
  - `git diff --check` passed; focused rendering and reaction regression
    coverage remains in Stages 2–3.
  - No migration or external-service checks apply to this presentation stage.
- Notes:
  - The existing outer score node remains the canonical reaction binding;
    Stage 2 will update its child targets without replacing the ratio.

## Stage 2 - Preserve structured score refreshes

- Changes:
  - Updated thread-reaction score refreshes to change QDB score and vote-count
    targets in place.
  - Recalculate the QDB score sign class after an update or rollback.
  - Retained whole-text score updates for score nodes without the QDB targets.
- Verification:
  - `node --check public/assets/thread_reactions.js` passed.
  - `php tests/run.php BrowserSigningNormalizationTest` passed (69/69).
  - `git diff --check` passed.
- Notes:
  - Dedicated structured-QDB refresh coverage remains Stage 3 work; existing
    generic reaction behavior passed unchanged.
