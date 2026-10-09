# QDB Score Display Emphasis — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_score_display_emphasis_step1_solution_assessment.md) · [Step 2](./qdb_score_display_emphasis_step2_feature_description.md) · [Step 3](./qdb_score_display_emphasis_step3_development_plan.md) · [Step 4](./qdb_score_display_emphasis_step4_implementation_summary.md)

## Problem

QDB colors an entire `(score/votes)` ratio, including its punctuation and vote
count, rather than emphasizing only the score. Live voting currently refreshes
that ratio as one text value, so the intended treatment must survive updates.

## User stories

- As a QDB reader, I want the score number to be bold and sign-colored so I
  can scan a quote's reception quickly.
- As a QDB reader, I want the parentheses, slash, and vote count to stay in
  the normal text color so the classic ratio remains easy to read.
- As a voter, I want the same presentation after a vote updates in place so
  the quote does not visually change to an incorrect format.

## Core requirements

- Every QDB quote listing and QDB quote permalink retains the `(score/votes)`
  format.
- Only the score number is bold; positive and negative scores retain their
  existing sign colors, while zero remains neutral.
- The parentheses, slash, and vote count use the default QDB text color.
- In-place vote updates preserve the same score-only presentation.
- Non-QDB score displays and score/vote calculations remain unchanged.

## Delivery scope

- **Work type:** application change.

## Completion boundary

- **Normal entry:** visit a QDB listing or a QDB quote permalink.
- **End-to-end outcome:** the same quote shows a bold, sign-colored score
  number inside a default-color `(score/votes)` ratio; voting refreshes it in
  place with that presentation intact.
- **Recovery:** a failed vote restores the previously displayed ratio and the
  existing reaction-feedback behavior.
- **Release condition:** the visual refinement is shippable alone, with
  coverage for both initial rendering and client-side refresh.

## Risks

- **Live refresh loses score-only emphasis.** Impact: a successful vote
  returns the display to a single styled ratio. Earliest validation: exercise
  an in-place score refresh before Step 3. Mitigation: treat the rendered
  score structure and its refresh behavior as one contract.
- **Listing and permalink treatments drift.** Impact: the same quote appears
  differently by entry point. Earliest validation: compare both initial
  renders while planning. Mitigation: apply one shared presentation contract
  to both existing QDB quote surfaces.
- **Theme styling leaks outside QDB.** Impact: generic forum score displays
  change unexpectedly. Earliest validation: inspect selector scope before
  Step 3. Mitigation: retain QDB-theme scoping and verify a non-QDB display.

## Shared component inventory

- QDB listing quote card — extended through the shared quote-score
  presentation contract; it remains the normal collection-surface renderer.
- QDB permalink root card — extended through that same contract; it remains
  the normal direct-link renderer.
- Thread-reaction score refresh — extended to preserve the contract after a
  vote; no API change is needed.
- QDB theme styles — extended only for the score-number emphasis; no new
  generic score component is needed.

## Simple user flow

1. A reader opens a QDB listing or direct quote link.
2. They see `(score/votes)`, with only the score number bold and sign-colored.
3. They vote on the quote.
4. The refreshed ratio retains the same selective emphasis.

## Success criteria

- Initial QDB listing and permalink markup presents only the numeric score as
  bold and sign-colored.
- Initial and refreshed QDB ratios keep parentheses, slash, and vote count in
  default QDB text color.
- Positive, negative, and zero-score cases retain their intended treatment.
- Existing reaction feedback and failed-vote restoration still work.
- Non-QDB score displays are unchanged.
