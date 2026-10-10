# QDB Quote Card Action Placement — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_quote_card_action_placement_step1_solution_assessment.md) · [Step 2](./qdb_quote_card_action_placement_step2_feature_description.md) · [Step 3](./qdb_quote_card_action_placement_step3_development_plan.md) · [Step 4](./qdb_quote_card_action_placement_step4_implementation_summary.md)

## Original Query

Please move the buttons to the top, after the score module, and change the
`[x]` button to a Flag button (with a flag symbol). Write Step 1 of
`docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

For both QDB listings and a QDB quote's numeric permalink page, show the vote
and flag controls in the quote header immediately after its score. Replace the
cryptic `[X]` flag affordance with a visible flag symbol and the word “Flag”,
while retaining the existing flag reaction, disabled state, and accessible
label.

## Problem

QDB displays its caption vote and flag controls below quote text, separating
them from the score they affect and making the flag action hard to recognize.

## Options

### Option A — Move each page's existing controls independently

Reposition the listing-card and permalink-page controls in their respective
templates and relabel only the flag control.

- Pros: small, direct markup changes.
- Cons: duplicates QDB control layout and makes the two surfaces easier to
  drift apart.

### Option B — Add a shared QDB quote-action component (Recommended)

Render one shared QDB control group directly after the score in each quote
header; it retains each existing button's reaction data and state while
presenting the flag action as `⚑ Flag`.

- Pros: keeps listings and permalink pages visually and behaviorally aligned;
  preserves caption voting, score updates, and flagging semantics; gives the
  feedback area a deliberate valid layout outside the compact header.
- Cons: requires a small template refactor and focused coverage of both
  surfaces.

## Recommendation

Adopt Option B. This is a viable vertical slice: a visitor sees the score,
then its caption-vote controls and `⚑ Flag` in one header row, can vote or
flag normally, and receives the existing optimistic state and feedback. The
scope is presentation only; it must not change caption selection, tag values,
scoring, or moderation behavior.

Waiting for "Approved Step 1" before drafting Step 2.
