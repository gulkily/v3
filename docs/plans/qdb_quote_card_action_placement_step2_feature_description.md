# QDB Quote Card Action Placement — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_quote_card_action_placement_step1_solution_assessment.md) · [Step 2](./qdb_quote_card_action_placement_step2_feature_description.md) · [Step 3](./qdb_quote_card_action_placement_step3_development_plan.md) · [Step 4](./qdb_quote_card_action_placement_step4_implementation_summary.md)

## Problem

QDB separates score-affecting controls from the score and represents flagging
as `[X]`, which is not self-explanatory. Readers need one compact, consistent
header action area on quote listings and quote permalink pages.

## User Stories

- As a QDB reader, I want voting controls immediately after a quote's score so
  their purpose and effect are easy to scan.
- As a QDB reader, I want a flag symbol and the word “Flag” so I can identify
  the moderation action without interpreting `[X]`.
- As a voter or flagger, I want the controls to retain their current outcome
  and state so the presentation change does not alter reactions.

## Core Requirements

- On QDB listing, search, random, top, and numeric-permalink quote views,
  place the caption vote controls and `⚑ Flag` immediately after the score.
- Retain the current caption labels, arrows, tags, score updates, flag action,
  disabled state, optimistic feedback, and accessible action names.
- Keep response feedback legible without placing invalid block feedback markup
  inside the compact header.
- Do not change non-QDB cards, reaction API behavior, caption selection, tag
  scoring, or moderation policy.
- Update QDB's introductory reaction guidance to describe the recognizable
  Flag action rather than `[X]`.

## Delivery Scope

- **Work type:** application change — QDB card presentation, welcome guidance,
  focused regression coverage, and Step 4 summary.
- **Out of scope:** reaction semantics, scoring/catalog changes, a new flag
  workflow, and non-QDB presentation changes.

## Completion Boundary

- **Normal entry:** a reader opens any QDB quote collection or a numeric quote
  permalink.
- **End-to-end outcome:** score then caption vote controls and `⚑ Flag` appear
  in the header; voting and flagging succeed or recover using existing UI
  feedback.
- **Recovery:** a failed reaction restores its usable control and reports the
  current error without a false score or flag state.
- **Release condition:** focused rendering and reaction-script coverage passes
  for listings and permalinks, with no non-QDB regression.

## Risks

- **Header crowding:** long caption labels or narrow screens could wrap poorly.
  *Earliest validation:* render a longest seeded caption pair. *Mitigation:*
  retain responsive button-row behavior and test its compact layout.
- **Surface drift:** listing and permalink views may diverge. *Earliest
  validation:* render both with the same quote fixture. *Mitigation:* use one
  shared QDB action presentation.
- **Reaction feedback placement:** moving controls can hide or invalidate
  feedback. *Earliest validation:* exercise successful and failed vote/flag
  updates. *Mitigation:* preserve the existing feedback targets outside the
  header control group.

## Shared Component Inventory

- `quote_card.php` and the QDB branch of `thread_root_card.php` render the
  current listing and permalink controls; extend them through one shared QDB
  action component.
- `thread_reactions.js` and the signed thread/post-tag APIs supply current
  reaction behavior; reuse unchanged.
- `qdb_welcome.php` documents the controls; update its wording only.
- Non-QDB post/thread cards render distinct controls; leave unchanged.

## Simple User Flow

1. A reader opens `/latest`, `/search`, `/random`, `/top`, or `/<quote-number>`.
2. The quote header shows its number, score, caption vote arrows, and `⚑ Flag`.
3. The reader votes or flags; the established state, score update, and feedback
   behavior occurs.

## Success Criteria

- Every QDB listing and numeric permalink places all three controls directly
  after the score and above the quote body.
- The flag control visibly includes a flag symbol and “Flag”; its accessible
  name still identifies flagging the quote for review.
- Existing vote-caption tags, flag records, score totals, disabled states, and
  success/failure feedback remain correct.
- No non-QDB reaction markup or behavior changes.

Waiting for "Approved Step 2" before drafting Step 3.
