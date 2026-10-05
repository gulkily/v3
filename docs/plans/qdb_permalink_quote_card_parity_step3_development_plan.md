# QDB Permalink Quote-Card Parity — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_permalink_quote_card_parity_step1_solution_assessment.md) · [Step 2](./qdb_permalink_quote_card_parity_step2_feature_description.md) · [Step 3](./qdb_permalink_quote_card_parity_step3_development_plan.md) · [Step 4](./qdb_permalink_quote_card_parity_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** clicking a quote's `#N` on the listing to reach its
  permalink; voting (upvote, downvote, flag) from a listing card or from
  the permalink.
- **End-to-end outcome:** the permalink matches its listing card (header,
  `(score/votes)` readout, upvote/downvote/flag controls); a vote from
  either surface updates the same readout in place; no qdb quote offers
  Like; no quote shows a ratio like `(5/0)`.
- **Required recovery:** an already-voted quote shows its vote as pressed
  and disabled on both surfaces; a failed vote shows the existing
  reaction-feedback message and leaves the score unchanged.
- **Deployment boundary:** after deploy, rebuild the read model
  (`./v3 rebuild`) so existing qdb totals pick up legacy-Like counting;
  verify `/api/read_model_status` reports ready. No schema change.
- **Release condition:** complete after Stage 4; the listing and permalink
  both work end to end.

## Key Risks

- **High risk:** vote totals are derived in two paths (full rebuild and
  incremental update). If the qdb-only rule is applied to one and not the
  other, scores silently diverge depending on how a quote was last
  touched.
  - Impact: inconsistent `(score/votes)` for qdb quotes, which is user-visible.
  - Early warning / validation: Stage 1's rebuild-vs-incremental parity test.
  - Mitigation: one shared site-aware predicate used by both paths.
- Existing read model keeps old totals until rebuilt.
  - Impact: legacy Likes stay uncounted until the rebuild runs.
  - Early warning / validation: `/api/read_model_status` and a spot check
    of a quote with a historical Like.
  - Mitigation: rebuild is part of the deployment boundary above.
- Listing script change affects board, top, random, and search pages.
  - Impact: a regression on other profiles' pages.
  - Early warning / validation: Stage 4 checks zenmemes and chouse pages
    render identically.
  - Mitigation: load the script only when the qdb profile is active.

## Stage 1
- Goal: on qdb only, existing `like` votes count toward a quote's vote
  total, in both the full rebuild and the incremental update.
- Dependencies: none.
- Expected changes: a site-aware vote-counting predicate (qdb counts
  `like` as a vote tag; other profiles unchanged), used in
  `ReadModelBuilder` and `IncrementalReadModelUpdater` where vote tags
  are counted today.
- Verification approach: a qdb thread with a Like shows a denominator of
  at least 1; full rebuild and incremental update produce identical totals
  for the same records; zenmemes totals unchanged.
- Risks or open questions:
  - Impact / early warning / mitigation: see the High risk above.
- Canonical components/API contracts touched: `TagScore` (predicate),
  `ReadModelBuilder`, `IncrementalReadModelUpdater`.

## Stage 2
- Goal: a qdb quote's permalink root card shows the listing's header,
  `(score/votes)` readout, and upvote/downvote/flag buttons, with no Like.
- Dependencies: Stage 1 (so the readout is coherent).
- Expected changes: `thread_root_card.php` gains a qdb branch that renders
  the score readout and the three vote controls with `quote_card.php`'s
  markup and data attributes, in place of Like/Flag. Everything else in the
  card (agent reply, Codex, analysis, reply list) untouched.
- Verification approach: a qdb permalink's HTML contains the readout and
  upvote/downvote/flag controls and no Like control; a zenmemes thread's
  root card is byte-identical to before.
- Risks or open questions:
  - Impact: the pressed state is wrong until Stage 3 supplies viewer state.
  - Early warning / validation: Stage 3's pressed-state check.
  - Mitigation: Stage 3 is required before the feature is considered done.
- Canonical components/API contracts touched: `thread_root_card.php`
  (qdb branch), `quote_card.php` (reference only).

## Stage 3
- Goal: the permalink shows the viewer's existing upvote, downvote, and
  flag as pressed and disabled.
- Dependencies: Stage 2.
- Expected changes: `ThreadAndPostPageController` looks up the viewer's
  upvote and downvote state for the root thread (same lookup the listing
  uses) and passes it to `thread.php`, which passes it to the root card.
- Verification approach: a viewer who previously upvoted a quote sees its
  permalink's upvote as pressed and disabled; a fresh viewer sees no state.
- Risks or open questions:
  - Impact: visitors think their vote didn't register.
  - Early warning / validation: the pressed-state check above.
  - Mitigation: reuse the listing's lookup rather than a new query.
- Canonical components/API contracts touched:
  `ThreadAndPostPageController`, `thread.php`, `ViewerTagLookup` (existing).

## Stage 4
- Goal: the listing's upvote, downvote, and flag buttons work on qdb.
- Dependencies: Stage 2 (so both surfaces share one vote control set).
- Expected changes: `BoardPageController` `board()`, `random()`, and
  `search()` add `thread_reactions.js` to their script lists only when the
  qdb profile is active.
- Verification approach: on qdb, `/latest`, `/top`, `/random`, and
  `/search` pages include the script; zenmemes and chouse pages do not
  change. Live click behavior is not driven in a real browser here; that
  limitation is recorded in the Step 4 summary.
- Risks or open questions:
  - Impact / early warning / mitigation: see Key Risks, third bullet.
- Canonical components/API contracts touched: `BoardPageController`
  (script list only); `thread_reactions.js` (unchanged).
