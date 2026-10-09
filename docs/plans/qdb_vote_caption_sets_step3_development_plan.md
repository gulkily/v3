# QDB Vote Caption Sets — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_vote_caption_sets_step1_solution_assessment.md) · [Step 2](./qdb_vote_caption_sets_step2_feature_description.md) · [Step 3](./qdb_vote_caption_sets_step3_development_plan.md) · [Step 4](./qdb_vote_caption_sets_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a QDB reader opens a quote listing, random/search page, or
  permalink and presses a displayed caption vote.
- **End-to-end outcome:** one active pair appears per page; its canonical tag
  is written; its configured polarity changes score and vote count once.
- **Required recovery:** unknown/inactive tags are rejected; a prior caption or
  legacy vote blocks a second QDB vote; write failure restores the control and
  score display.
- **Deployment/external verification:** initialize the private caption database
  before accepting QDB writes, then build/review a release artifact. A hosted
  browser test is outside this slice.
- **Release condition:** catalog, full/incremental derivation, signed write,
  listing/permalink, static-artifact, and profile-isolation tests pass.

## Key Risks

- **High risk:** a caption accepted by the write path is not scored identically
  by full and incremental read-model paths. Impact: corrupt ratings. Early
  validation: same-record parity fixture. Mitigation: one catalog-backed QDB
  scoring policy supplied to both paths.
- **High risk:** random page pairs let a voter cast semantic duplicate votes.
  Impact: inflated totals. Early validation: vote, render a different pair,
  retry. Mitigation: one cross-catalog QDB-vote lookup used by rendering and
  write idempotency, including legacy `upvote`/`downvote`.
- Configured values could rewrite historical semantics on rebuild. Impact:
  non-reproducible scores. Early validation: alter active state then rebuild a
  historical-caption fixture. Mitigation: retain every known tag and immutable
  polarity; activation controls presentation only.

## Stage 1

- Goal: establish durable, private QDB caption catalog storage and archive seed.
- Dependencies: approved Steps 1–2.
- Expected changes: add configuration-path resolution and a SQLite catalog store
  with bootstrap/schema checks; seed every archived set with stable canonical
  tags, polarity, active state, and duplicate Funny/Awful set.
- Verification approach: isolated-store tests prove bootstrap is idempotent,
  archived rows are complete, and tags/polarities are unique and valid.
- Risks or open questions: Impact: a read-model rebuild erases catalog edits.
  Early warning / validation: bootstrap after rebuild. Mitigation: use a
  dedicated private configuration database, not the rebuildable read model.
- Canonical components/API contracts touched: new QDB caption database-config
  and catalog-store contract; application configuration wiring.

## Stage 2

- Goal: expose one safe catalog/policy for selection, tag lookup, and scoring.
- Dependencies: Stage 1.
- Expected changes: introduce catalog operations conceptually shaped as
  `selectActivePair()`, `captionForTag(string $tag)`, and
  `isQdbVoteTag(string $tag)`; reject incomplete active sets and preserve
  retired tags for historical score lookup.
- Verification approach: selection returns only whole active pairs; duplicate
  rows remain independently selectable; unknown/inactive tags cannot be
  submitted, while retired historical tags still resolve for rebuilds.
- Risks or open questions: Impact: catalog changed while static HTML is live.
  Early warning / validation: submit a previously rendered retired tag.
  Mitigation: accept known historical tags for derivation but allow writes only
  for currently active tags.
- Canonical components/API contracts touched: QDB catalog policy, QDB
  presentation data contract.

## Stage 3

- Goal: derive QDB caption scores and vote totals identically in every path.
- Dependencies: Stage 2.
- Expected changes: compose catalog-backed caption scoring with existing
  `TagScore` behavior; use it in `ReadModelBuilder` and
  `IncrementalReadModelUpdater`, with one vote per identity across caption and
  legacy QDB vote forms and unchanged non-QDB calculations.
- Verification approach: compare full rebuild and incremental results for
  positive, negative, duplicate, legacy, retired, and generic-profile fixtures.
- Risks or open questions: Impact / early warning / mitigation: see first and
  third Key Risks.
- Canonical components/API contracts touched: `TagScore` boundary,
  `ReadModelBuilder`, `IncrementalReadModelUpdater`, thread score/vote fields.

## Stage 4

- Goal: safely write one caption tag per QDB quote and identity.
- Dependencies: Stages 2–3.
- Expected changes: extend `LocalWriteService::applyThreadTag()` validation and
  idempotency to recognize active QDB caption tags, reject non-quote/non-QDB
  use, and check all catalog plus legacy vote tags before writing; retain the
  existing signed thread-tag API and feedback protocol.
- Verification approach: API/write tests prove `good` and `keep-it` write and
  score correctly, inactive/unknown tags fail, and a later different caption
  writes no second vote.
- Risks or open questions: Impact: direct API callers bypass UI safeguards.
  Early warning / validation: direct request test for every rejection case.
  Mitigation: enforce the catalog and cross-catalog duplicate rule in the
  server-side writer, not JavaScript.
- Canonical components/API contracts touched: `LocalWriteService`,
  `TagApiController` (reused), canonical thread-label records,
  `thread_reactions.js` response contract.

## Stage 5

- Goal: render one caption pair and correct prior-vote state on QDB collection
  surfaces.
- Dependencies: Stages 2 and 4.
- Expected changes: select and pass one pair through QDB board, random, and
  search presentation; extend `QdbBoardPolicy` viewer state to represent any
  prior QDB vote; render arrow-plus-caption controls from `quote_card.php`.
- Verification approach: HTML and browser-runtime tests show uniform labels on
  a multi-card page, canonical `data-tag` values, action-oriented labels, and
  disabled state after a vote or different-pair reload.
- Risks or open questions: Impact: per-card selection breaks archival behavior.
  Early warning / validation: multi-card page fixture. Mitigation: select only
  in the page/controller boundary, never in a card partial.
- Canonical components/API contracts touched: `QdbExperience`,
  `BoardPageController`, `QdbBoardPolicy`, `quote_card.php`,
  `thread_reactions.js`.

## Stage 6

- Goal: give QDB permalinks the same caption-vote behavior and preserve static
  rendering semantics.
- Dependencies: Stages 2, 4, and 5.
- Expected changes: supply one selected pair and cross-catalog viewer state to
  the qdb branch of `ThreadAndPostPageController`/`thread_root_card.php`; ensure
  static and offline generation renders a build-time valid pair.
- Verification approach: permalink/listing parity test; generated QDB page
  contains a complete pair and valid tags; a static-page vote reaches the same
  server validation and update path.
- Risks or open questions: Impact: stale generated labels point to inactive
  tags. Early warning / validation: render, deactivate, then submit fixture.
  Mitigation: server rejects inactive writes with existing error feedback.
- Canonical components/API contracts touched: `ThreadAndPostPageController`,
  `thread.php`, `thread_root_card.php`, static/offline artifact builders.

## Stage 7

- Goal: prove release readiness and document the configuration dependency.
- Dependencies: Stages 1–6.
- Expected changes: add cross-path regression coverage, private-path/bootstrap
  documentation if needed, and Stage 4 verification evidence; no management UI.
- Verification approach: targeted catalog, score, API, quote-card/permalink,
  static-artifact, and browser-normalization suites; relevant full suite and
  `git diff --check`; manual local QDB vote/reload spot check.
- Risks or open questions: Impact: an unrelated profile changes. Early warning
  / validation: existing generic reaction fixtures. Mitigation: require them
  unchanged before release and halt if QDB configuration leaks across profiles.
- Canonical components/API contracts touched: QDB regression suites, existing
  generic reaction contracts, Step 4 implementation summary.

Waiting for "Approved Step 3" before beginning Step 4.
