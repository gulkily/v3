# QDB Score Display Emphasis — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_score_display_emphasis_step1_solution_assessment.md) · [Step 2](./qdb_score_display_emphasis_step2_feature_description.md) · [Step 3](./qdb_score_display_emphasis_step3_development_plan.md) · [Step 4](./qdb_score_display_emphasis_step4_implementation_summary.md)

## Original Query

For the qdb site, when quotes are displayed, I want the quote score to be
color-coded and bold, but I want the parentheses around the score and the /
symbol and the vote count to be default text color. Please tell me if you can
one-shot this, or if we should use docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

Please write Step 1.

## Problem

QDB displays each score ratio as one styled unit, so its score color also
affects the ratio punctuation and vote count, which obscures the intended
score-only emphasis.

## Options

### Option A — Give the score value its own presentation element (Recommended)

Keep the existing ratio as one readable value while giving only its score
number a dedicated semantic presentation target on every QDB quote surface.

- Pros: directly preserves default-color punctuation and vote count; makes
  score color and boldness explicit; applies consistently to listings and
  quote permalinks.
- Cons: quote-score rendering and its live vote refresh need to retain the
  same structure.

### Option B — Retain the single styled ratio and approximate selective color

Use a purely visual treatment on the existing full ratio to suggest that the
score is emphasized.

- Pros: limited markup change.
- Cons: cannot reliably distinguish adjacent characters in one text value;
  risks coloring the punctuation or vote count contrary to the request.

### Option C — Render score and vote metadata as separate display fields

Replace the compact ratio with separately styled score and vote-count labels.

- Pros: clear independent styling and future metadata flexibility.
- Cons: changes the classic QDB ratio format and exceeds the requested visual
  refinement.

## Recommendation

Adopt Option A. It is a small, releasable vertical slice: a visitor sees the
same classic `(score/votes)` ratio on a QDB listing or permalink, with only the
score number bold and sign-colored while all surrounding ratio text remains
default-color. It preserves the established display format and leaves generic
site profiles out of scope.

## Continuation Handoff

- Current evidence: both QDB listing and permalink cards render the ratio as
  one score node, and client-side voting refreshes that node as a whole.
- Scope boundary: preserve ratio text and live vote updates; do not change
  score calculation, vote counting, or non-QDB presentation.
- Resume point: review this Step 1. Do not create Step 2 until the user
  responds `Approved Step 1`.
