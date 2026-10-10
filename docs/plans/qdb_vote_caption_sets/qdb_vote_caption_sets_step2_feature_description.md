# QDB Vote Caption Sets — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_vote_caption_sets_step1_solution_assessment.md) · [Step 2](./qdb_vote_caption_sets_step2_feature_description.md) · [Step 3](./qdb_vote_caption_sets_step3_development_plan.md) · [Step 4](./qdb_vote_caption_sets_step4_implementation_summary.md)

## Problem

QDB's `+`/`-` controls write generic tags and omit the archive's per-page
caption pairs. Caption labels must instead be configured QDB vote tags whose
positive or negative values drive score and vote totals consistently.

## User Stories

- As a QDB reader, I want a page's vote buttons to show one playful matched
  pair beside up/down arrows, so voting resembles classic QDB.
- As a voter, I want pressing `Good`, `Funny`, `Trash It`, or another displayed
  label to add that label's canonical tag, so my vote has visible meaning.
- As a reader, I want every configured positive/negative caption vote to
  affect a quote's score and vote count correctly, so ratings remain trusted.
- As a maintainer, I want the caption catalog to be data-configurable, so its
  labels, active pairs, tags, and values stay one source of truth.

## Core Requirements

- Seed a QDB-only SQLite caption catalog from all archived sets, retaining
  active state and the separate duplicate Funny/Awful set; only active complete
  pairs are eligible for random page selection.
- Select one active pair once per rendered QDB page and use it for every quote
  vote control on that response. The control visibly combines its up/down
  arrow with the selected label and retains an action-oriented accessible name.
- A press writes the selected label's canonical tag (for example, `good` or
  `keep-it`), not `upvote`/`downvote`; the configured `+1`/`-1` value governs
  validation, score derivation, and vote-count derivation.
- A viewer may contribute only one QDB caption vote per quote. Existing
  caption-vote state must disable/recover correctly even when a later page
  chooses a different label pair; random captions cannot create extra votes.
- Preserve historical `upvote`/`downvote` records and their existing score
  effects. Do not alter non-QDB vote behavior, flagging, or other tag uses.

## Delivery Scope

- **Work type:** application change — QDB catalog, voting presentation/write
  path, derived scoring, regression coverage, and Step 4 summary.
- **Out of scope:** a caption-management UI, changes to generic-site reactions,
  modifying historical records, and changing quote selection/routing.

## Completion Boundary

- **Normal entry:** a visitor opens a QDB listing, random/search result, or
  quote permalink and presses a captioned vote arrow.
- **End-to-end outcome:** every control on the page shares one active caption
  pair; the pressed caption tag is saved; the same quote's score and vote count
  immediately reflect the configured value.
- **Recovery:** an unavailable/incomplete catalog fails safely without
  accepting an unknown vote tag; an already-voted quote remains non-repeatable;
  failed saves use existing reaction feedback without a false score update.
- **Release condition:** catalog, render, write, full/incremental derived-state,
  and browser reaction tests pass; old generic votes and non-QDB profiles retain
  their current results.

## Risks

- **Caption changes versus durable votes:** random page labels could let one
  voter submit multiple semantic equivalents. *Earliest validation:* vote once,
  reload to a different pair, and attempt another vote. *Mitigation:* determine
  prior QDB caption-vote state across the whole configured catalog, not only the
  currently displayed tag.
- **Split scoring sources:** a configured tag accepted by the write path but
  absent from either read-model path corrupts totals. *Earliest validation:*
  rebuild and incremental update of the same caption vote. *Mitigation:* use
  the catalog as the shared authority for validation and both derivations.
- **Static/offline pages:** their chosen pair can persist longer than a dynamic
  response. *Earliest validation:* render a generated page and vote from it.
  *Mitigation:* treat the generated page's build-time pair as its page choice;
  the server still validates its active configured tag.
- **Legacy generic tags:** existing `upvote`/`downvote` records could be lost
  or double-counted. *Earliest validation:* mixed legacy/caption fixture.
  *Mitigation:* preserve their established values and count each identity's QDB
  vote once across legacy and caption forms.

## Shared Component Inventory

- New QDB caption-catalog service/store — required authority for selectable
  pairs, canonical tags, polarity, and current catalog validity.
- `quote_card.php` and qdb branch of `thread_root_card.php` — extend the
  canonical listing/permalink vote controls; do not fork card markup.
- `QdbBoardPolicy` and `ThreadAndPostPageController` — extend viewer vote state
  so listing, random/search, and permalink surfaces all honor any prior QDB
  caption vote.
- `thread_reactions.js` and the existing thread-tag API — reuse the canonical
  signed reaction transport with the selected canonical caption tag.
- `LocalWriteService`, `TagScore`, `ReadModelBuilder`, and
  `IncrementalReadModelUpdater` — extend the shared validation and full/
  incremental score and vote-total derivation; generic profiles remain intact.

## Simple User Flow

1. A reader opens `/latest`; QDB selects one active pair, such as `Good`/`Bad`.
2. Each visible quote displays those labels beside its up/down arrows.
3. The reader presses `Good`; the system saves `good` and adds its configured
   `+1` to that quote's score and one vote to its total.
4. On another page with `Funny`/`Awful`, the same quote recognizes that reader's
   prior QDB vote and does not permit a second caption vote.

## Success Criteria

- Each dynamic QDB page uses one active complete pair; inactive and incomplete
  pairs are never presented, and duplicate active sets retain their selection
  weight.
- Pressing every seeded positive/negative caption creates its canonical tag and
  changes score by its configured `+1`/`-1`, with one corresponding vote.
- A second caption choice by the same identity on the same QDB quote produces
  no additional canonical vote or score change, even after a different pair is
  displayed.
- Full rebuild and incremental update produce equal score and vote totals for
  caption and legacy-vote fixtures.
- QDB listings, random/search results, and quote permalinks share the behavior;
  non-QDB reaction controls and scores are unchanged.

Waiting for "Approved Step 2" before drafting Step 3.
